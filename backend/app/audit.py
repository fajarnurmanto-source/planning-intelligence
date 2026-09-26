import hashlib
import json
from sqlalchemy import select
from sqlalchemy.orm import Session
from .models import AuditEvent

def record_event(db: Session, event_type: str, message: str, entity_type: str | None = None, entity_id: str | None = None, metadata: dict | None = None):
    last = db.execute(select(AuditEvent).order_by(AuditEvent.id.desc()).limit(1)).scalar_one_or_none()
    previous_hash = last.event_hash if last else None
    payload = {
        "event_type": event_type,
        "entity_type": entity_type,
        "entity_id": entity_id,
        "message": message,
        "metadata": metadata or {},
        "previous_hash": previous_hash,
    }
    event_hash = hashlib.sha256(json.dumps(payload, sort_keys=True, ensure_ascii=False, default=str).encode("utf-8")).hexdigest()
    ev = AuditEvent(event_type=event_type, entity_type=entity_type, entity_id=entity_id, message=message, event_data=metadata or {}, previous_hash=previous_hash, event_hash=event_hash)
    db.add(ev)
    db.commit()
    db.refresh(ev)
    return ev
