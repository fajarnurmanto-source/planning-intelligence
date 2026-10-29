"""Deterministic, conservative label/target candidate scoring.

This module deliberately does not infer table columns, legal compatibility or
policy alignment. A candidate is evidence for subsequent human review only.
"""

import re
import unicodedata


def plain(value: str) -> str:
    value = unicodedata.normalize("NFKD", value).lower()
    return re.sub(r"\s+", " ", re.sub(r"[^a-z0-9]+", " ", value)).strip()


def target_text(value: str) -> str:
    return re.sub(r"\s+", "", value.lower().replace("–", "-").replace("—", "-").replace(".", ","))


def anchor_token(indicator: str) -> str:
    """Choose a distinctive label token, never an arbitrary numeric target."""
    tokens = [token for token in plain(indicator).split() if len(token) > 2]
    return max(tokens, key=len) if tokens else ""


def evaluate_excerpt(indicator: str, target: str, excerpt: str) -> tuple[float, bool] | None:
    label = plain(indicator)
    words = plain(excerpt)
    tokens = set(token for token in label.split() if len(token) > 2)
    if not tokens:
        return None
    observed = set(words.split())
    coverage = len(tokens & observed) / len(tokens)
    exact_label = bool(label and label in words)
    if not exact_label and coverage < 0.75:
        return None
    wanted = target_text(target)
    same_target = len(wanted) >= 4 and wanted in target_text(excerpt)
    return (10 if same_target else 0) + (2 if exact_label else coverage), same_target


def best_candidate(indicator: str, target: str, candidates: list[dict]) -> dict:
    """Each candidate contains id, page_start, unit_fingerprint and excerpt."""
    best = None
    score = -1.0
    for candidate in candidates:
        evaluated = evaluate_excerpt(indicator, target, candidate["excerpt"])
        if evaluated is None:
            continue
        value, same_target = evaluated
        if value > score:
            score = value
            best = dict(candidate, same_target=same_target)
        if value >= 12:
            break
    if best is None:
        return {
            "comparison_status": "NO_TEXT_MATCH",
            "comparison_page": None,
            "comparison_source_unit_id": None,
            "comparison_source_fingerprint": None,
            "comparison_excerpt": None,
        }
    return {
        "comparison_status": "TARGET_TEXT_NEAR_LABEL" if best["same_target"] else "INDICATOR_TEXT_FOUND",
        "comparison_page": best["page_start"],
        "comparison_source_unit_id": best["id"],
        "comparison_source_fingerprint": best["unit_fingerprint"],
        "comparison_excerpt": best["excerpt"][:500],
    }
