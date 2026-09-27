import uuid
from datetime import date, datetime
from sqlalchemy import (
    String,
    Integer,
    Date,
    DateTime,
    ForeignKey,
    Text,
    BigInteger,
    Boolean,
    UniqueConstraint,
    CheckConstraint,
    Index,
)
from sqlalchemy.dialects.postgresql import UUID, JSONB
from sqlalchemy.orm import Mapped, mapped_column, relationship
from .db import Base

def uid():
    return uuid.uuid4()

class Jurisdiction(Base):
    __tablename__ = "jurisdictions"
    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uid)
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    level: Mapped[str] = mapped_column(String(50), default="CITY")
    parent_id: Mapped[uuid.UUID | None] = mapped_column(UUID(as_uuid=True), ForeignKey("jurisdictions.id"), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

class PlanningPeriod(Base):
    __tablename__ = "planning_periods"
    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uid)
    jurisdiction_id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), ForeignKey("jurisdictions.id"), nullable=False)
    name: Mapped[str] = mapped_column(String(150), nullable=False)
    start_year: Mapped[int] = mapped_column(Integer, nullable=False)
    end_year: Mapped[int] = mapped_column(Integer, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

class Document(Base):
    __tablename__ = "documents"
    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uid)
    jurisdiction_id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), ForeignKey("jurisdictions.id"), nullable=False)
    planning_period_id: Mapped[uuid.UUID | None] = mapped_column(UUID(as_uuid=True), ForeignKey("planning_periods.id"), nullable=True)
    doc_type: Mapped[str] = mapped_column(String(80), nullable=False)
    title: Mapped[str] = mapped_column(String(500), nullable=False)
    original_name: Mapped[str] = mapped_column(String(500), nullable=False)
    relative_path: Mapped[str] = mapped_column(String(1000), nullable=False)
    sha256: Mapped[str] = mapped_column(String(64), nullable=False)
    size_bytes: Mapped[int] = mapped_column(BigInteger, nullable=False)
    version_label: Mapped[str | None] = mapped_column(String(100), nullable=True)
    uploaded_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)
    versions: Mapped[list["DocumentVersion"]] = relationship(
        back_populates="document",
        cascade="all, delete-orphan",
        foreign_keys="DocumentVersion.document_id",
    )

class DocumentVersion(Base):
    """
    Immutable record of one physical file version of a logical Document.

    The legacy file fields on Document remain for V0.0 compatibility and point
    to the latest uploaded version. All parsing/provenance work must reference
    DocumentVersion, never the mutable compatibility fields on Document.
    """
    __tablename__ = "document_versions"
    __table_args__ = (
        UniqueConstraint("document_id", "version_number", name="uq_document_versions_document_number"),
        UniqueConstraint("document_id", "sha256", name="uq_document_versions_document_sha256"),
        CheckConstraint("version_number >= 1", name="ck_document_versions_number_positive"),
        CheckConstraint("char_length(sha256) = 64", name="ck_document_versions_sha256_length"),
        Index("ix_document_versions_document_uploaded", "document_id", "uploaded_at"),
    )

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uid)
    document_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("documents.id", ondelete="CASCADE"), nullable=False
    )
    version_number: Mapped[int] = mapped_column(Integer, nullable=False)
    version_label: Mapped[str | None] = mapped_column(String(100), nullable=True)
    original_name: Mapped[str] = mapped_column(String(500), nullable=False)
    relative_path: Mapped[str] = mapped_column(String(1000), nullable=False)
    sha256: Mapped[str] = mapped_column(String(64), nullable=False)
    size_bytes: Mapped[int] = mapped_column(BigInteger, nullable=False)
    mime_type: Mapped[str | None] = mapped_column(String(200), nullable=True)
    file_extension: Mapped[str | None] = mapped_column(String(32), nullable=True)
    source_kind: Mapped[str] = mapped_column(String(50), default="UPLOAD")
    supersedes_version_id: Mapped[uuid.UUID | None] = mapped_column(
        UUID(as_uuid=True), ForeignKey("document_versions.id", ondelete="SET NULL"), nullable=True
    )
    uploaded_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

    document: Mapped["Document"] = relationship(
        back_populates="versions",
        foreign_keys=[document_id],
    )

class ParsingRun(Base):
    """
    One reproducible parser execution against one immutable DocumentVersion.
    """
    __tablename__ = "parsing_runs"
    __table_args__ = (
        CheckConstraint(
            "status IN ('PENDING','RUNNING','SUCCEEDED','FAILED')",
            name="ck_parsing_runs_status",
        ),
        CheckConstraint("char_length(input_sha256) = 64", name="ck_parsing_runs_input_sha256_length"),
        CheckConstraint("char_length(parser_config_sha256) = 64", name="ck_parsing_runs_config_sha256_length"),
        CheckConstraint("char_length(run_fingerprint) = 64", name="ck_parsing_runs_fingerprint_length"),
        Index("ix_parsing_runs_document_version", "document_version_id", "created_at"),
        Index("ix_parsing_runs_fingerprint", "run_fingerprint"),
    )

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uid)
    document_version_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("document_versions.id", ondelete="CASCADE"), nullable=False
    )
    parser_name: Mapped[str] = mapped_column(String(120), nullable=False)
    parser_version: Mapped[str] = mapped_column(String(80), nullable=False)
    parser_config: Mapped[dict] = mapped_column(JSONB, default=dict)
    parser_config_sha256: Mapped[str] = mapped_column(String(64), nullable=False)
    input_sha256: Mapped[str] = mapped_column(String(64), nullable=False)
    run_fingerprint: Mapped[str] = mapped_column(String(64), nullable=False)
    status: Mapped[str] = mapped_column(String(30), default="PENDING")
    page_count: Mapped[int | None] = mapped_column(Integer, nullable=True)
    source_unit_count: Mapped[int | None] = mapped_column(Integer, nullable=True)
    result_sha256: Mapped[str | None] = mapped_column(String(64), nullable=True)
    error_message: Mapped[str | None] = mapped_column(Text, nullable=True)
    started_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    completed_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

class SourceUnit(Base):
    """
    Smallest addressable evidence-bearing unit emitted by a parser.

    Examples: PAGE, HEADING, PARAGRAPH, LIST_ITEM, TABLE, TABLE_ROW, TABLE_CELL.
    The locator JSON stores format-specific coordinates while common page and
    sequence fields remain directly queryable.
    """
    __tablename__ = "source_units"
    __table_args__ = (
        UniqueConstraint("parsing_run_id", "sequence_index", name="uq_source_units_run_sequence"),
        UniqueConstraint("parsing_run_id", "unit_fingerprint", name="uq_source_units_run_fingerprint"),
        CheckConstraint("sequence_index >= 0", name="ck_source_units_sequence_nonnegative"),
        CheckConstraint("page_start IS NULL OR page_start >= 1", name="ck_source_units_page_start"),
        CheckConstraint(
            "page_end IS NULL OR page_start IS NULL OR page_end >= page_start",
            name="ck_source_units_page_range",
        ),
        CheckConstraint("char_length(content_sha256) = 64", name="ck_source_units_content_sha256_length"),
        CheckConstraint("char_length(unit_fingerprint) = 64", name="ck_source_units_fingerprint_length"),
        Index("ix_source_units_version_page", "document_version_id", "page_start"),
        Index("ix_source_units_run_type", "parsing_run_id", "unit_type"),
        Index("ix_source_units_content_sha256", "content_sha256"),
    )

    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uid)
    parsing_run_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("parsing_runs.id", ondelete="CASCADE"), nullable=False
    )
    document_version_id: Mapped[uuid.UUID] = mapped_column(
        UUID(as_uuid=True), ForeignKey("document_versions.id", ondelete="CASCADE"), nullable=False
    )
    parent_unit_id: Mapped[uuid.UUID | None] = mapped_column(
        UUID(as_uuid=True), ForeignKey("source_units.id", ondelete="CASCADE"), nullable=True
    )
    unit_type: Mapped[str] = mapped_column(String(50), nullable=False)
    sequence_index: Mapped[int] = mapped_column(Integer, nullable=False)
    ordinal_in_parent: Mapped[int | None] = mapped_column(Integer, nullable=True)
    page_start: Mapped[int | None] = mapped_column(Integer, nullable=True)
    page_end: Mapped[int | None] = mapped_column(Integer, nullable=True)
    section_path: Mapped[list] = mapped_column(JSONB, default=list)
    locator: Mapped[dict] = mapped_column(JSONB, default=dict)
    structural_path: Mapped[str | None] = mapped_column(String(1000), nullable=True)
    raw_text: Mapped[str | None] = mapped_column(Text, nullable=True)
    normalized_text: Mapped[str | None] = mapped_column(Text, nullable=True)
    content_sha256: Mapped[str] = mapped_column(String(64), nullable=False)
    unit_fingerprint: Mapped[str] = mapped_column(String(64), nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

class Regulation(Base):
    __tablename__ = "regulations"
    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uid)
    title: Mapped[str] = mapped_column(String(500), nullable=False)
    regulation_type: Mapped[str] = mapped_column(String(80), nullable=False)
    number: Mapped[str | None] = mapped_column(String(100), nullable=True)
    year: Mapped[int | None] = mapped_column(Integer, nullable=True)
    jurisdiction_scope: Mapped[str] = mapped_column(String(255), default="NATIONAL")
    sector_scope: Mapped[str] = mapped_column(String(255), default="GENERAL")
    status: Mapped[str] = mapped_column(String(50), default="DRAFT")
    valid_from: Mapped[date | None] = mapped_column(Date, nullable=True)
    valid_to: Mapped[date | None] = mapped_column(Date, nullable=True)
    source_url: Mapped[str | None] = mapped_column(Text, nullable=True)
    original_name: Mapped[str | None] = mapped_column(String(500), nullable=True)
    relative_path: Mapped[str | None] = mapped_column(String(1000), nullable=True)
    sha256: Mapped[str | None] = mapped_column(String(64), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

class Audit(Base):
    __tablename__ = "audits"
    id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), primary_key=True, default=uid)
    jurisdiction_id: Mapped[uuid.UUID] = mapped_column(UUID(as_uuid=True), ForeignKey("jurisdictions.id"), nullable=False)
    planning_period_id: Mapped[uuid.UUID | None] = mapped_column(UUID(as_uuid=True), ForeignKey("planning_periods.id"), nullable=True)
    title: Mapped[str] = mapped_column(String(500), nullable=False)
    audit_type: Mapped[str] = mapped_column(String(100), default="FOUNDATION_TEST")
    status: Mapped[str] = mapped_column(String(50), default="DRAFT")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

class AuditEvent(Base):
    __tablename__ = "audit_events"
    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    event_type: Mapped[str] = mapped_column(String(100), nullable=False)
    entity_type: Mapped[str | None] = mapped_column(String(100), nullable=True)
    entity_id: Mapped[str | None] = mapped_column(String(100), nullable=True)
    message: Mapped[str] = mapped_column(Text, nullable=False)
    event_data: Mapped[dict] = mapped_column("metadata", JSONB, default=dict)
    previous_hash: Mapped[str | None] = mapped_column(String(64), nullable=True)
    event_hash: Mapped[str] = mapped_column(String(64), nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow)

class AppSetting(Base):
    __tablename__ = "app_settings"
    key: Mapped[str] = mapped_column(String(100), primary_key=True)
    value: Mapped[str] = mapped_column(Text, nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=datetime.utcnow, onupdate=datetime.utcnow)
