"""Read-only M2 synthetic demonstration and bounded review export API."""
from __future__ import annotations

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from .pipeline import apply_synthetic_review, render_synthetic_report
from .synthetic_demo import cached_synthetic_result
from .substantive_m3 import screen_substantive_candidates
from .relation_m3 import allowed_relations, screen_declared_relations

router = APIRouter(prefix="/api/m2", tags=["M2 Synthetic Workbench"])


class ReviewRequest(BaseModel):
    pipeline_report_sha256: str = Field(min_length=64, max_length=64)
    decisions: dict[str, str] = Field(default_factory=dict, max_length=32)


@router.get("/synthetic-demo")
def synthetic_demo():
    return cached_synthetic_result()


@router.post("/synthetic-review")
def synthetic_review(request: ReviewRequest):
    pipeline = cached_synthetic_result()
    if request.pipeline_report_sha256 != pipeline["report_sha256"]:
        raise HTTPException(status_code=409, detail="M2_PIPELINE_DIGEST_MISMATCH")
    try:
        review = apply_synthetic_review(pipeline, request.decisions)
        report = render_synthetic_report(pipeline, review)
    except (PermissionError, ValueError) as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
    return {"review": review, "report_markdown": report}


m3_router = APIRouter(prefix="/api/m3", tags=["M3 Synthetic Target Screen"])


@m3_router.get("/synthetic-target-screen")
def m3_synthetic_target_screen():
    """Pre-review only: reuse the cached synthetic M2 parser result."""
    try:
        return screen_substantive_candidates(cached_synthetic_result())
    except (PermissionError, ValueError) as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc


class RelationClaim(BaseModel):
    from_role: str = Field(min_length=4, max_length=8)
    from_entity_id: str = Field(min_length=1, max_length=80)
    to_role: str = Field(min_length=4, max_length=8)
    to_entity_id: str = Field(min_length=1, max_length=80)
    relation: str = Field(min_length=1, max_length=60)
    source_role: str = Field(default="", max_length=8)
    source_unit_fingerprint: str = Field(default="", max_length=64)
    quote: str = Field(default="", max_length=800)


class RelationReviewRequest(BaseModel):
    pipeline_report_sha256: str = Field(min_length=64, max_length=64)
    claims: list[RelationClaim] = Field(default_factory=list, max_length=16)


@m3_router.get("/relation-types")
def m3_relation_types():
    """Reuse the existing G2 ontology in the browser rather than retyping it."""
    return allowed_relations()


@m3_router.post("/synthetic-relation-review")
def m3_synthetic_relation_review(request: RelationReviewRequest):
    """The user, not the model, explicitly declares relation/quote candidates."""
    pipeline = cached_synthetic_result()
    if request.pipeline_report_sha256 != pipeline["report_sha256"]:
        raise HTTPException(status_code=409, detail="M3_M2_PIPELINE_DIGEST_MISMATCH")
    try:
        return screen_declared_relations(
            pipeline, [claim.model_dump() for claim in request.claims])
    except (PermissionError, ValueError) as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc

