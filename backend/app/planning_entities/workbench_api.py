"""Read-only M2 synthetic demonstration and bounded review export API."""
from __future__ import annotations

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, Field

from .pipeline import apply_synthetic_review, render_synthetic_report
from .synthetic_demo import cached_synthetic_result

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
