import hashlib
import os
import re
import shutil
from datetime import date, datetime, timezone
from pathlib import Path
from uuid import UUID
from fastapi import FastAPI, Depends, HTTPException, UploadFile, File, Form
from sqlalchemy import select, func
from sqlalchemy.orm import Session
from .db import get_db
from .models import (
    Jurisdiction,
    PlanningPeriod,
    Document,
    DocumentVersion,
    ParsingRun,
    SourceUnit,
    Regulation,
    Audit,
    AuditEvent,
    AppSetting,
)
from .schemas import ProjectCreate, AuditCreate, SettingUpdate
from .audit import record_event
from .parser import (
    DEFAULT_CONFIG,
    PARSER_NAME,
    PARSER_VERSION,
    build_run_fingerprint,
    config_sha256,
    parse_document,
)

app = FastAPI(title="Planning & Decision Intelligence V0.1A", version="0.1.0-alpha.2")
DATA_DIR = Path(os.getenv("APP_DATA_DIR", "/data"))
FILES_DIR = DATA_DIR / "files"
REG_DIR = DATA_DIR / "regulations"
FILES_DIR.mkdir(parents=True, exist_ok=True)
REG_DIR.mkdir(parents=True, exist_ok=True)

def clean_filename(name: str) -> str:
    name = Path(name).name
    return re.sub(r"[^A-Za-z0-9._ -]", "_", name)[:180]

def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()

def save_upload(file: UploadFile, target_dir: Path) -> tuple[Path, str, int]:
    target_dir.mkdir(parents=True, exist_ok=True)
    safe = clean_filename(file.filename or "document.bin")
    target = target_dir / safe
    stem, suffix, idx = target.stem, target.suffix, 1
    while target.exists():
        target = target_dir / f"{stem}_{idx}{suffix}"
        idx += 1
    with target.open("wb") as out:
        shutil.copyfileobj(file.file, out)
    return target, sha256_file(target), target.stat().st_size

@app.get("/health")
def health():
    return {
        "status": "ok",
        "version": "0.1.0-alpha.2",
        "ai_provider": os.getenv("AI_PROVIDER", "NO_AI"),
        "capability": "DETERMINISTIC_DOCUMENT_PARSING",
    }

@app.get("/api/projects")
def list_projects(db: Session = Depends(get_db)):
    jurisdictions = db.execute(select(Jurisdiction).order_by(Jurisdiction.created_at.desc())).scalars().all()
    out = []
    for j in jurisdictions:
        periods = db.execute(select(PlanningPeriod).where(PlanningPeriod.jurisdiction_id == j.id).order_by(PlanningPeriod.start_year)).scalars().all()
        out.append({"id": str(j.id), "name": j.name, "level": j.level, "periods": [{"id": str(p.id), "name": p.name, "start_year": p.start_year, "end_year": p.end_year} for p in periods]})
    return out

@app.post("/api/projects")
def create_project(payload: ProjectCreate, db: Session = Depends(get_db)):
    if payload.end_year < payload.start_year:
        raise HTTPException(400, "End year must be >= start year")
    normalized = payload.jurisdiction_name.strip()
    j = db.execute(
        select(Jurisdiction).where(
            func.lower(Jurisdiction.name) == normalized.lower(),
            Jurisdiction.level == payload.jurisdiction_level,
        ).order_by(Jurisdiction.created_at.asc())
    ).scalars().first()
    created_j = False
    if not j:
        j = Jurisdiction(name=normalized, level=payload.jurisdiction_level)
        db.add(j); db.flush(); created_j = True
    p = db.execute(
        select(PlanningPeriod).where(
            PlanningPeriod.jurisdiction_id == j.id,
            func.lower(PlanningPeriod.name) == payload.period_name.strip().lower(),
            PlanningPeriod.start_year == payload.start_year,
            PlanningPeriod.end_year == payload.end_year,
        )
    ).scalars().first()
    if p:
        if created_j: db.rollback()
        return {"jurisdiction_id": str(j.id), "period_id": str(p.id), "created": False}
    p = PlanningPeriod(jurisdiction_id=j.id, name=payload.period_name.strip(), start_year=payload.start_year, end_year=payload.end_year)
    db.add(p); db.commit(); db.refresh(j); db.refresh(p)
    record_event(db, "PROJECT_CREATED", f"Project {j.name} / {p.name} created", "jurisdiction", str(j.id), {"period_id": str(p.id)})
    return {"jurisdiction_id": str(j.id), "period_id": str(p.id), "created": True}

@app.get("/api/documents")
def list_documents(db: Session = Depends(get_db)):
    docs = db.execute(select(Document).order_by(Document.uploaded_at.desc())).scalars().all()
    out = []
    for d in docs:
        latest = db.execute(
            select(DocumentVersion)
            .where(DocumentVersion.document_id == d.id)
            .order_by(DocumentVersion.version_number.desc())
            .limit(1)
        ).scalar_one_or_none()
        out.append({
            "id": str(d.id),
            "title": d.title,
            "doc_type": d.doc_type,
            "original_name": d.original_name,
            "sha256": d.sha256,
            "size_bytes": d.size_bytes,
            "jurisdiction_id": str(d.jurisdiction_id),
            "planning_period_id": str(d.planning_period_id) if d.planning_period_id else None,
            "uploaded_at": d.uploaded_at.isoformat(),
            "latest_version_id": str(latest.id) if latest else None,
            "latest_version_number": latest.version_number if latest else None,
        })
    return out

@app.post("/api/documents")
def upload_document(
    jurisdiction_id: UUID = Form(...),
    planning_period_id: UUID | None = Form(None),
    doc_type: str = Form(...),
    title: str = Form(...),
    version_label: str | None = Form(None),
    file: UploadFile = File(...),
    db: Session = Depends(get_db),
):
    j = db.get(Jurisdiction, jurisdiction_id)
    if not j:
        raise HTTPException(404, "Jurisdiction not found")
    if planning_period_id and not db.get(PlanningPeriod, planning_period_id):
        raise HTTPException(404, "Planning period not found")

    target, digest, size_bytes = save_upload(file, FILES_DIR / str(jurisdiction_id))
    safe = target.name
    relative_path = str(target.relative_to(DATA_DIR))

    d = Document(
        jurisdiction_id=jurisdiction_id,
        planning_period_id=planning_period_id,
        doc_type=doc_type,
        title=title,
        original_name=safe,
        relative_path=relative_path,
        sha256=digest,
        size_bytes=size_bytes,
        version_label=version_label,
    )
    db.add(d)
    db.flush()

    v = DocumentVersion(
        document_id=d.id,
        version_number=1,
        version_label=version_label,
        original_name=safe,
        relative_path=relative_path,
        sha256=digest,
        size_bytes=size_bytes,
        mime_type=file.content_type,
        file_extension=target.suffix.lower().lstrip(".") or None,
        source_kind="UPLOAD",
    )
    db.add(v)
    db.commit()
    db.refresh(d)
    db.refresh(v)

    record_event(
        db,
        "DOCUMENT_UPLOADED",
        f"Document uploaded: {title}",
        "document",
        str(d.id),
        {
            "sha256": digest,
            "doc_type": doc_type,
            "document_version_id": str(v.id),
            "version_number": 1,
        },
    )
    return {
        "id": str(d.id),
        "document_version_id": str(v.id),
        "version_number": 1,
        "sha256": digest,
    }

@app.get("/api/documents/{document_id}/versions")
def list_document_versions(document_id: UUID, db: Session = Depends(get_db)):
    d = db.get(Document, document_id)
    if not d:
        raise HTTPException(404, "Document not found")
    versions = db.execute(
        select(DocumentVersion)
        .where(DocumentVersion.document_id == document_id)
        .order_by(DocumentVersion.version_number.asc())
    ).scalars().all()
    return [{
        "id": str(v.id),
        "document_id": str(v.document_id),
        "version_number": v.version_number,
        "version_label": v.version_label,
        "original_name": v.original_name,
        "relative_path": v.relative_path,
        "sha256": v.sha256,
        "size_bytes": v.size_bytes,
        "mime_type": v.mime_type,
        "file_extension": v.file_extension,
        "source_kind": v.source_kind,
        "supersedes_version_id": str(v.supersedes_version_id) if v.supersedes_version_id else None,
        "uploaded_at": v.uploaded_at.isoformat(),
    } for v in versions]

@app.post("/api/documents/{document_id}/versions")
def upload_document_version(
    document_id: UUID,
    version_label: str | None = Form(None),
    file: UploadFile = File(...),
    db: Session = Depends(get_db),
):
    d = db.get(Document, document_id)
    if not d:
        raise HTTPException(404, "Document not found")

    target, digest, size_bytes = save_upload(file, FILES_DIR / str(d.jurisdiction_id))
    relative_path = str(target.relative_to(DATA_DIR))

    duplicate = db.execute(
        select(DocumentVersion).where(
            DocumentVersion.document_id == document_id,
            DocumentVersion.sha256 == digest,
        )
    ).scalar_one_or_none()
    if duplicate:
        try:
            target.unlink(missing_ok=True)
        except Exception:
            pass
        return {
            "id": str(duplicate.id),
            "document_id": str(document_id),
            "version_number": duplicate.version_number,
            "sha256": duplicate.sha256,
            "created": False,
            "reason": "IDENTICAL_VERSION_ALREADY_REGISTERED",
        }

    previous = db.execute(
        select(DocumentVersion)
        .where(DocumentVersion.document_id == document_id)
        .order_by(DocumentVersion.version_number.desc())
        .limit(1)
    ).scalar_one_or_none()
    next_number = (previous.version_number if previous else 0) + 1

    v = DocumentVersion(
        document_id=document_id,
        version_number=next_number,
        version_label=version_label,
        original_name=target.name,
        relative_path=relative_path,
        sha256=digest,
        size_bytes=size_bytes,
        mime_type=file.content_type,
        file_extension=target.suffix.lower().lstrip(".") or None,
        source_kind="UPLOAD",
        supersedes_version_id=previous.id if previous else None,
    )
    db.add(v)

    # Keep V0.0-compatible document fields pointed at the latest version.
    d.original_name = target.name
    d.relative_path = relative_path
    d.sha256 = digest
    d.size_bytes = size_bytes
    d.version_label = version_label

    db.commit()
    db.refresh(v)
    db.refresh(d)

    record_event(
        db,
        "DOCUMENT_VERSION_UPLOADED",
        f"Document version {next_number} uploaded: {d.title}",
        "document_version",
        str(v.id),
        {
            "document_id": str(document_id),
            "version_number": next_number,
            "sha256": digest,
            "supersedes_version_id": str(previous.id) if previous else None,
        },
    )
    return {
        "id": str(v.id),
        "document_id": str(document_id),
        "version_number": next_number,
        "sha256": digest,
        "created": True,
    }



def _version_extension(v: DocumentVersion) -> str | None:
    if v.file_extension:
        return v.file_extension.lower().lstrip(".")
    suffix = Path(v.original_name or v.relative_path or "").suffix.lower().lstrip(".")
    return suffix or None


def _parsing_run_payload(run: ParsingRun, reused: bool = False):
    return {
        "id": str(run.id),
        "document_version_id": str(run.document_version_id),
        "parser_name": run.parser_name,
        "parser_version": run.parser_version,
        "parser_config_sha256": run.parser_config_sha256,
        "input_sha256": run.input_sha256,
        "run_fingerprint": run.run_fingerprint,
        "status": run.status,
        "page_count": run.page_count,
        "source_unit_count": run.source_unit_count,
        "result_sha256": run.result_sha256,
        "error_message": run.error_message,
        "started_at": run.started_at.isoformat() if run.started_at else None,
        "completed_at": run.completed_at.isoformat() if run.completed_at else None,
        "created_at": run.created_at.isoformat(),
        "reused": reused,
    }


@app.post("/api/document-versions/{document_version_id}/parse")
def parse_document_version(
    document_version_id: UUID,
    force: bool = False,
    db: Session = Depends(get_db),
):
    v = db.get(DocumentVersion, document_version_id)
    if not v:
        raise HTTPException(404, "Document version not found")

    path = DATA_DIR / v.relative_path
    if not path.exists() or not path.is_file():
        raise HTTPException(409, "Document-version file is missing from local storage")

    actual_sha = sha256_file(path)
    if actual_sha.lower() != v.sha256.lower():
        raise HTTPException(
            409,
            "Document-version checksum mismatch. Parsing refused to protect provenance integrity.",
        )

    config = dict(DEFAULT_CONFIG)
    cfg_sha = config_sha256(config)
    fingerprint = build_run_fingerprint(v.sha256, config)

    if not force:
        existing = db.execute(
            select(ParsingRun)
            .where(
                ParsingRun.document_version_id == v.id,
                ParsingRun.run_fingerprint == fingerprint,
                ParsingRun.status == "SUCCEEDED",
            )
            .order_by(ParsingRun.completed_at.desc())
            .limit(1)
        ).scalar_one_or_none()
        if existing:
            return _parsing_run_payload(existing, reused=True)

    run = ParsingRun(
        document_version_id=v.id,
        parser_name=PARSER_NAME,
        parser_version=PARSER_VERSION,
        parser_config=config,
        parser_config_sha256=cfg_sha,
        input_sha256=v.sha256,
        run_fingerprint=fingerprint,
        status="RUNNING",
        started_at=datetime.now(timezone.utc),
    )
    db.add(run)
    db.commit()
    db.refresh(run)

    try:
        parsed = parse_document(
            path,
            input_sha256=v.sha256,
            extension=_version_extension(v),
            config=config,
        )

        sequence_to_id: dict[int, UUID] = {}
        rows: list[SourceUnit] = []
        import uuid as _uuid

        for unit in parsed["units"]:
            unit_id = _uuid.uuid4()
            sequence_to_id[unit.sequence_index] = unit_id
            parent_id = (
                sequence_to_id.get(unit.parent_sequence_index)
                if unit.parent_sequence_index is not None
                else None
            )
            rows.append(
                SourceUnit(
                    id=unit_id,
                    parsing_run_id=run.id,
                    document_version_id=v.id,
                    parent_unit_id=parent_id,
                    unit_type=unit.unit_type,
                    sequence_index=unit.sequence_index,
                    ordinal_in_parent=unit.ordinal_in_parent,
                    page_start=unit.page_start,
                    page_end=unit.page_end,
                    section_path=unit.section_path,
                    locator=unit.locator,
                    structural_path=unit.structural_path,
                    raw_text=unit.raw_text,
                    normalized_text=unit.normalized_text,
                    content_sha256=unit.content_sha256,
                    unit_fingerprint=unit.unit_fingerprint,
                )
            )

        db.add_all(rows)
        run.status = "SUCCEEDED"
        run.page_count = parsed["page_count"]
        run.source_unit_count = parsed["source_unit_count"]
        run.result_sha256 = parsed["result_sha256"]
        run.completed_at = datetime.now(timezone.utc)
        db.commit()
        db.refresh(run)

        record_event(
            db,
            "DOCUMENT_PARSED",
            f"Document version parsed deterministically with {run.source_unit_count} source units",
            "parsing_run",
            str(run.id),
            {
                "document_version_id": str(v.id),
                "parser_name": PARSER_NAME,
                "parser_version": PARSER_VERSION,
                "run_fingerprint": run.run_fingerprint,
                "result_sha256": run.result_sha256,
                "source_unit_count": run.source_unit_count,
                "page_count": run.page_count,
                "force": force,
            },
        )
        return _parsing_run_payload(run, reused=False)

    except Exception as exc:
        db.rollback()
        failed = db.get(ParsingRun, run.id)
        if failed:
            failed.status = "FAILED"
            failed.error_message = str(exc)[:8000]
            failed.completed_at = datetime.now(timezone.utc)
            db.commit()
            record_event(
                db,
                "DOCUMENT_PARSE_FAILED",
                "Deterministic document parsing failed",
                "parsing_run",
                str(failed.id),
                {
                    "document_version_id": str(v.id),
                    "parser_name": PARSER_NAME,
                    "parser_version": PARSER_VERSION,
                    "error": str(exc)[:1000],
                },
            )
        raise HTTPException(422, f"Deterministic parsing failed: {exc}")


@app.get("/api/document-versions/{document_version_id}/parsing-runs")
def list_parsing_runs(document_version_id: UUID, db: Session = Depends(get_db)):
    if not db.get(DocumentVersion, document_version_id):
        raise HTTPException(404, "Document version not found")
    rows = db.execute(
        select(ParsingRun)
        .where(ParsingRun.document_version_id == document_version_id)
        .order_by(ParsingRun.created_at.desc())
    ).scalars().all()
    return [_parsing_run_payload(row) for row in rows]


@app.get("/api/parsing-runs/{run_id}")
def get_parsing_run(run_id: UUID, db: Session = Depends(get_db)):
    run = db.get(ParsingRun, run_id)
    if not run:
        raise HTTPException(404, "Parsing run not found")
    return _parsing_run_payload(run)


@app.get("/api/parsing-runs/{run_id}/source-units")
def list_source_units(
    run_id: UUID,
    offset: int = 0,
    limit: int = 200,
    db: Session = Depends(get_db),
):
    run = db.get(ParsingRun, run_id)
    if not run:
        raise HTTPException(404, "Parsing run not found")
    safe_offset = max(offset, 0)
    safe_limit = min(max(limit, 1), 1000)
    rows = db.execute(
        select(SourceUnit)
        .where(SourceUnit.parsing_run_id == run_id)
        .order_by(SourceUnit.sequence_index)
        .offset(safe_offset)
        .limit(safe_limit)
    ).scalars().all()
    return [{
        "id": str(u.id),
        "parsing_run_id": str(u.parsing_run_id),
        "document_version_id": str(u.document_version_id),
        "parent_unit_id": str(u.parent_unit_id) if u.parent_unit_id else None,
        "unit_type": u.unit_type,
        "sequence_index": u.sequence_index,
        "ordinal_in_parent": u.ordinal_in_parent,
        "page_start": u.page_start,
        "page_end": u.page_end,
        "section_path": u.section_path,
        "locator": u.locator,
        "structural_path": u.structural_path,
        "raw_text": u.raw_text,
        "normalized_text": u.normalized_text,
        "content_sha256": u.content_sha256,
        "unit_fingerprint": u.unit_fingerprint,
    } for u in rows]


@app.get("/api/regulations")
def list_regulations(db: Session = Depends(get_db)):
    regs = db.execute(select(Regulation).order_by(Regulation.created_at.desc())).scalars().all()
    return [{"id": str(r.id), "title": r.title, "regulation_type": r.regulation_type, "number": r.number, "year": r.year, "jurisdiction_scope": r.jurisdiction_scope, "sector_scope": r.sector_scope, "status": r.status, "sha256": r.sha256} for r in regs]

@app.post("/api/regulations")
def create_regulation(
    title: str = Form(...), regulation_type: str = Form(...), number: str | None = Form(None), year: int | None = Form(None), jurisdiction_scope: str = Form("NATIONAL"), sector_scope: str = Form("GENERAL"), status: str = Form("DRAFT"), valid_from: date | None = Form(None), valid_to: date | None = Form(None), source_url: str | None = Form(None), file: UploadFile | None = File(None), db: Session = Depends(get_db)
):
    original_name = relative_path = digest = None
    if file and file.filename:
        original_name = clean_filename(file.filename)
        target = REG_DIR / original_name
        stem, suffix, idx = target.stem, target.suffix, 1
        while target.exists(): target = REG_DIR / f"{stem}_{idx}{suffix}"; idx += 1
        with target.open("wb") as out: shutil.copyfileobj(file.file, out)
        digest = sha256_file(target); relative_path = str(target.relative_to(DATA_DIR))
    existing = db.execute(
        select(Regulation).where(
            func.lower(Regulation.title) == title.strip().lower(),
            Regulation.regulation_type == regulation_type,
            Regulation.number == number,
            Regulation.year == year,
            Regulation.jurisdiction_scope == jurisdiction_scope,
        ).order_by(Regulation.created_at.asc())
    ).scalars().first()
    if existing:
        if relative_path:
            try: (DATA_DIR / relative_path).unlink(missing_ok=True)
            except Exception: pass
        return {"id": str(existing.id), "created": False}
    r = Regulation(title=title.strip(), regulation_type=regulation_type, number=number, year=year, jurisdiction_scope=jurisdiction_scope, sector_scope=sector_scope, status=status, valid_from=valid_from, valid_to=valid_to, source_url=source_url, original_name=original_name, relative_path=relative_path, sha256=digest)
    db.add(r); db.commit(); db.refresh(r)
    record_event(db, "REGULATION_REGISTERED", f"Regulation registered: {title}", "regulation", str(r.id), {"status": status, "sha256": digest})
    return {"id": str(r.id), "created": True}

@app.get("/api/audits")
def list_audits(db: Session = Depends(get_db)):
    audits = db.execute(select(Audit).order_by(Audit.created_at.desc())).scalars().all()
    return [{"id": str(a.id), "title": a.title, "audit_type": a.audit_type, "status": a.status, "jurisdiction_id": str(a.jurisdiction_id), "planning_period_id": str(a.planning_period_id) if a.planning_period_id else None, "created_at": a.created_at.isoformat()} for a in audits]

@app.post("/api/audits")
def create_audit(payload: AuditCreate, db: Session = Depends(get_db)):
    if not db.get(Jurisdiction, payload.jurisdiction_id): raise HTTPException(404, "Jurisdiction not found")
    a = Audit(jurisdiction_id=payload.jurisdiction_id, planning_period_id=payload.planning_period_id, title=payload.title, audit_type=payload.audit_type)
    db.add(a); db.commit(); db.refresh(a)
    record_event(db, "AUDIT_CREATED", f"Audit created: {a.title}", "audit", str(a.id), {"audit_type": a.audit_type})
    return {"id": str(a.id)}

@app.get("/api/events")
def list_events(limit: int = 100, db: Session = Depends(get_db)):
    events = db.execute(select(AuditEvent).order_by(AuditEvent.id.desc()).limit(min(max(limit,1),500))).scalars().all()
    return [{"id": e.id, "event_type": e.event_type, "entity_type": e.entity_type, "entity_id": e.entity_id, "message": e.message, "metadata": e.event_data, "previous_hash": e.previous_hash, "event_hash": e.event_hash, "created_at": e.created_at.isoformat()} for e in events]

@app.get("/api/settings")
def list_settings(db: Session = Depends(get_db)):
    rows = db.execute(select(AppSetting)).scalars().all()
    settings = {r.key: r.value for r in rows}
    settings.setdefault("AI_PROVIDER", os.getenv("AI_PROVIDER", "NO_AI"))
    return settings

@app.put("/api/settings/{key}")
def set_setting(key: str, payload: SettingUpdate, db: Session = Depends(get_db)):
    row = db.get(AppSetting, key)
    if row: row.value = payload.value
    else: db.add(AppSetting(key=key, value=payload.value))
    db.commit(); record_event(db, "SETTING_CHANGED", f"Setting {key} updated", "setting", key, {"value": payload.value})
    return {"key": key, "value": payload.value}
