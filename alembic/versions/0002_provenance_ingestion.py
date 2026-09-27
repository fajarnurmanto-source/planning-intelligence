"""V0.1A provenance-first ingestion foundation

Adds immutable document versions, reproducible parsing runs, addressable source
units, and links the existing evidence table to version/source-unit provenance.
No AI dependency is introduced.
"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

revision = "0002_provenance_ingestion"
down_revision = "0001_foundation"
branch_labels = None
depends_on = None


def upgrade():
    op.create_table(
        "document_versions",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("gen_random_uuid()")),
        sa.Column("document_id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("version_number", sa.Integer(), nullable=False),
        sa.Column("version_label", sa.String(100), nullable=True),
        sa.Column("original_name", sa.String(500), nullable=False),
        sa.Column("relative_path", sa.String(1000), nullable=False),
        sa.Column("sha256", sa.String(64), nullable=False),
        sa.Column("size_bytes", sa.BigInteger(), nullable=False),
        sa.Column("mime_type", sa.String(200), nullable=True),
        sa.Column("file_extension", sa.String(32), nullable=True),
        sa.Column("source_kind", sa.String(50), nullable=False, server_default="UPLOAD"),
        sa.Column("supersedes_version_id", postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column("uploaded_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.text("now()")),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.text("now()")),
        sa.ForeignKeyConstraint(["document_id"], ["documents.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["supersedes_version_id"], ["document_versions.id"], ondelete="SET NULL"),
        sa.UniqueConstraint("document_id", "version_number", name="uq_document_versions_document_number"),
        sa.UniqueConstraint("document_id", "sha256", name="uq_document_versions_document_sha256"),
        sa.CheckConstraint("version_number >= 1", name="ck_document_versions_number_positive"),
        sa.CheckConstraint("char_length(sha256) = 64", name="ck_document_versions_sha256_length"),
    )
    op.create_index(
        "ix_document_versions_document_uploaded",
        "document_versions",
        ["document_id", "uploaded_at"],
    )

    # Preserve all V0.0 uploads as immutable version 1 records.
    op.execute(
        """
        INSERT INTO document_versions (
            id, document_id, version_number, version_label, original_name,
            relative_path, sha256, size_bytes, mime_type, file_extension,
            source_kind, supersedes_version_id, uploaded_at, created_at
        )
        SELECT
            gen_random_uuid(), d.id, 1, d.version_label, d.original_name,
            d.relative_path, d.sha256, d.size_bytes, NULL,
            NULL,
            'LEGACY_BACKFILL', NULL, d.uploaded_at, d.uploaded_at
        FROM documents d
        WHERE NOT EXISTS (
            SELECT 1
            FROM document_versions dv
            WHERE dv.document_id = d.id
        )
        """
    )

    op.create_table(
        "parsing_runs",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("gen_random_uuid()")),
        sa.Column("document_version_id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("parser_name", sa.String(120), nullable=False),
        sa.Column("parser_version", sa.String(80), nullable=False),
        sa.Column("parser_config", postgresql.JSONB(), nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("parser_config_sha256", sa.String(64), nullable=False),
        sa.Column("input_sha256", sa.String(64), nullable=False),
        sa.Column("run_fingerprint", sa.String(64), nullable=False),
        sa.Column("status", sa.String(30), nullable=False, server_default="PENDING"),
        sa.Column("page_count", sa.Integer(), nullable=True),
        sa.Column("source_unit_count", sa.Integer(), nullable=True),
        sa.Column("result_sha256", sa.String(64), nullable=True),
        sa.Column("error_message", sa.Text(), nullable=True),
        sa.Column("started_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("completed_at", sa.DateTime(timezone=True), nullable=True),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.text("now()")),
        sa.ForeignKeyConstraint(["document_version_id"], ["document_versions.id"], ondelete="CASCADE"),
        sa.CheckConstraint(
            "status IN ('PENDING','RUNNING','SUCCEEDED','FAILED')",
            name="ck_parsing_runs_status",
        ),
        sa.CheckConstraint("char_length(input_sha256) = 64", name="ck_parsing_runs_input_sha256_length"),
        sa.CheckConstraint("char_length(parser_config_sha256) = 64", name="ck_parsing_runs_config_sha256_length"),
        sa.CheckConstraint("char_length(run_fingerprint) = 64", name="ck_parsing_runs_fingerprint_length"),
    )
    op.create_index(
        "ix_parsing_runs_document_version",
        "parsing_runs",
        ["document_version_id", "created_at"],
    )
    op.create_index("ix_parsing_runs_fingerprint", "parsing_runs", ["run_fingerprint"])

    op.create_table(
        "source_units",
        sa.Column("id", postgresql.UUID(as_uuid=True), primary_key=True, server_default=sa.text("gen_random_uuid()")),
        sa.Column("parsing_run_id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("document_version_id", postgresql.UUID(as_uuid=True), nullable=False),
        sa.Column("parent_unit_id", postgresql.UUID(as_uuid=True), nullable=True),
        sa.Column("unit_type", sa.String(50), nullable=False),
        sa.Column("sequence_index", sa.Integer(), nullable=False),
        sa.Column("ordinal_in_parent", sa.Integer(), nullable=True),
        sa.Column("page_start", sa.Integer(), nullable=True),
        sa.Column("page_end", sa.Integer(), nullable=True),
        sa.Column("section_path", postgresql.JSONB(), nullable=False, server_default=sa.text("'[]'::jsonb")),
        sa.Column("locator", postgresql.JSONB(), nullable=False, server_default=sa.text("'{}'::jsonb")),
        sa.Column("structural_path", sa.String(1000), nullable=True),
        sa.Column("raw_text", sa.Text(), nullable=True),
        sa.Column("normalized_text", sa.Text(), nullable=True),
        sa.Column("content_sha256", sa.String(64), nullable=False),
        sa.Column("unit_fingerprint", sa.String(64), nullable=False),
        sa.Column("created_at", sa.DateTime(timezone=True), nullable=False, server_default=sa.text("now()")),
        sa.ForeignKeyConstraint(["parsing_run_id"], ["parsing_runs.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["document_version_id"], ["document_versions.id"], ondelete="CASCADE"),
        sa.ForeignKeyConstraint(["parent_unit_id"], ["source_units.id"], ondelete="CASCADE"),
        sa.UniqueConstraint("parsing_run_id", "sequence_index", name="uq_source_units_run_sequence"),
        sa.UniqueConstraint("parsing_run_id", "unit_fingerprint", name="uq_source_units_run_fingerprint"),
        sa.CheckConstraint("sequence_index >= 0", name="ck_source_units_sequence_nonnegative"),
        sa.CheckConstraint("page_start IS NULL OR page_start >= 1", name="ck_source_units_page_start"),
        sa.CheckConstraint(
            "page_end IS NULL OR page_start IS NULL OR page_end >= page_start",
            name="ck_source_units_page_range",
        ),
        sa.CheckConstraint("char_length(content_sha256) = 64", name="ck_source_units_content_sha256_length"),
        sa.CheckConstraint("char_length(unit_fingerprint) = 64", name="ck_source_units_fingerprint_length"),
    )
    op.create_index(
        "ix_source_units_version_page",
        "source_units",
        ["document_version_id", "page_start"],
    )
    op.create_index(
        "ix_source_units_run_type",
        "source_units",
        ["parsing_run_id", "unit_type"],
    )
    op.create_index(
        "ix_source_units_content_sha256",
        "source_units",
        ["content_sha256"],
    )

    # Extend the already-reserved evidence table so later analytical claims can
    # point to exact immutable versions and exact parsed source units.
    op.add_column(
        "evidence",
        sa.Column("document_version_id", postgresql.UUID(as_uuid=True), nullable=True),
    )
    op.add_column(
        "evidence",
        sa.Column("source_unit_id", postgresql.UUID(as_uuid=True), nullable=True),
    )
    op.create_foreign_key(
        "fk_evidence_document_version_id",
        "evidence",
        "document_versions",
        ["document_version_id"],
        ["id"],
        ondelete="SET NULL",
    )
    op.create_foreign_key(
        "fk_evidence_source_unit_id",
        "evidence",
        "source_units",
        ["source_unit_id"],
        ["id"],
        ondelete="SET NULL",
    )
    op.create_index("ix_evidence_document_version_id", "evidence", ["document_version_id"])
    op.create_index("ix_evidence_source_unit_id", "evidence", ["source_unit_id"])


def downgrade():
    op.drop_index("ix_evidence_source_unit_id", table_name="evidence")
    op.drop_index("ix_evidence_document_version_id", table_name="evidence")
    op.drop_constraint("fk_evidence_source_unit_id", "evidence", type_="foreignkey")
    op.drop_constraint("fk_evidence_document_version_id", "evidence", type_="foreignkey")
    op.drop_column("evidence", "source_unit_id")
    op.drop_column("evidence", "document_version_id")

    op.drop_index("ix_source_units_content_sha256", table_name="source_units")
    op.drop_index("ix_source_units_run_type", table_name="source_units")
    op.drop_index("ix_source_units_version_page", table_name="source_units")
    op.drop_table("source_units")

    op.drop_index("ix_parsing_runs_fingerprint", table_name="parsing_runs")
    op.drop_index("ix_parsing_runs_document_version", table_name="parsing_runs")
    op.drop_table("parsing_runs")

    op.drop_index("ix_document_versions_document_uploaded", table_name="document_versions")
    op.drop_table("document_versions")
