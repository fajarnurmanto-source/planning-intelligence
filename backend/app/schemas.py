from pydantic import BaseModel, Field
from uuid import UUID

class ProjectCreate(BaseModel):
    jurisdiction_name: str = Field(min_length=2, max_length=255)
    jurisdiction_level: str = "CITY"
    period_name: str = Field(min_length=2, max_length=150)
    start_year: int = Field(ge=1900, le=2200)
    end_year: int = Field(ge=1900, le=2200)

class AuditCreate(BaseModel):
    jurisdiction_id: UUID
    planning_period_id: UUID | None = None
    title: str
    audit_type: str = "FOUNDATION_TEST"

class SettingUpdate(BaseModel):
    value: str
