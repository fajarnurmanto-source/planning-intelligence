"""V0.0 foundation schema + V1.2 schema-ready entities"""
from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql
revision = "0001_foundation"
down_revision = None
branch_labels = None
depends_on = None

def upgrade():
    op.execute('CREATE EXTENSION IF NOT EXISTS vector')
    op.execute('CREATE EXTENSION IF NOT EXISTS pgcrypto')
    op.create_table('jurisdictions', sa.Column('id', postgresql.UUID(as_uuid=True), primary_key=True), sa.Column('name', sa.String(255), nullable=False), sa.Column('level', sa.String(50), nullable=False), sa.Column('parent_id', postgresql.UUID(as_uuid=True), nullable=True), sa.Column('created_at', sa.DateTime(timezone=True), nullable=False), sa.ForeignKeyConstraint(['parent_id'], ['jurisdictions.id']))
    op.create_table('planning_periods', sa.Column('id', postgresql.UUID(as_uuid=True), primary_key=True), sa.Column('jurisdiction_id', postgresql.UUID(as_uuid=True), nullable=False), sa.Column('name', sa.String(150), nullable=False), sa.Column('start_year', sa.Integer(), nullable=False), sa.Column('end_year', sa.Integer(), nullable=False), sa.Column('created_at', sa.DateTime(timezone=True), nullable=False), sa.ForeignKeyConstraint(['jurisdiction_id'], ['jurisdictions.id']))
    op.create_table('documents', sa.Column('id', postgresql.UUID(as_uuid=True), primary_key=True), sa.Column('jurisdiction_id', postgresql.UUID(as_uuid=True), nullable=False), sa.Column('planning_period_id', postgresql.UUID(as_uuid=True)), sa.Column('doc_type', sa.String(80), nullable=False), sa.Column('title', sa.String(500), nullable=False), sa.Column('original_name', sa.String(500), nullable=False), sa.Column('relative_path', sa.String(1000), nullable=False), sa.Column('sha256', sa.String(64), nullable=False), sa.Column('size_bytes', sa.BigInteger(), nullable=False), sa.Column('version_label', sa.String(100)), sa.Column('uploaded_at', sa.DateTime(timezone=True), nullable=False), sa.ForeignKeyConstraint(['jurisdiction_id'], ['jurisdictions.id']), sa.ForeignKeyConstraint(['planning_period_id'], ['planning_periods.id']))
    op.create_table('regulations', sa.Column('id', postgresql.UUID(as_uuid=True), primary_key=True), sa.Column('title', sa.String(500), nullable=False), sa.Column('regulation_type', sa.String(80), nullable=False), sa.Column('number', sa.String(100)), sa.Column('year', sa.Integer()), sa.Column('jurisdiction_scope', sa.String(255), nullable=False), sa.Column('sector_scope', sa.String(255), nullable=False), sa.Column('status', sa.String(50), nullable=False), sa.Column('valid_from', sa.Date()), sa.Column('valid_to', sa.Date()), sa.Column('source_url', sa.Text()), sa.Column('original_name', sa.String(500)), sa.Column('relative_path', sa.String(1000)), sa.Column('sha256', sa.String(64)), sa.Column('created_at', sa.DateTime(timezone=True), nullable=False))
    op.create_table('audits', sa.Column('id', postgresql.UUID(as_uuid=True), primary_key=True), sa.Column('jurisdiction_id', postgresql.UUID(as_uuid=True), nullable=False), sa.Column('planning_period_id', postgresql.UUID(as_uuid=True)), sa.Column('title', sa.String(500), nullable=False), sa.Column('audit_type', sa.String(100), nullable=False), sa.Column('status', sa.String(50), nullable=False), sa.Column('created_at', sa.DateTime(timezone=True), nullable=False), sa.ForeignKeyConstraint(['jurisdiction_id'], ['jurisdictions.id']), sa.ForeignKeyConstraint(['planning_period_id'], ['planning_periods.id']))
    op.create_table('audit_events', sa.Column('id', sa.BigInteger(), primary_key=True, autoincrement=True), sa.Column('event_type', sa.String(100), nullable=False), sa.Column('entity_type', sa.String(100)), sa.Column('entity_id', sa.String(100)), sa.Column('message', sa.Text(), nullable=False), sa.Column('metadata', postgresql.JSONB(), nullable=False), sa.Column('previous_hash', sa.String(64)), sa.Column('event_hash', sa.String(64), nullable=False), sa.Column('created_at', sa.DateTime(timezone=True), nullable=False))
    op.create_table('app_settings', sa.Column('key', sa.String(100), primary_key=True), sa.Column('value', sa.Text(), nullable=False), sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False))

    # Schema-ready V1.2 entities. UI/logic will be activated in later milestones.
    simple_tables = {
      'sources': [('source_type','varchar(80)'),('title','varchar(500)'),('uri','text'),('authority_level','varchar(80)'),('reliability','varchar(50)'),('metadata','jsonb')],
      'rules': [('code','varchar(120)'),('title','varchar(500)'),('status','varchar(50)'),('scope','jsonb'),('metadata','jsonb')],
      'rule_versions': [('rule_id','uuid'),('version','varchar(50)'),('valid_from','date'),('valid_to','date'),('logic','jsonb'),('source_refs','jsonb')],
      'graph_nodes': [('jurisdiction_id','uuid'),('planning_period_id','uuid'),('node_type','varchar(100)'),('label','text'),('official_id','varchar(255)'),('concept_id','varchar(255)'),('properties','jsonb')],
      'graph_edges': [('source_node_id','uuid'),('target_node_id','uuid'),('relationship_type','varchar(100)'),('temporal_type','varchar(50)'),('continuity_type','varchar(50)'),('confidence','double precision'),('properties','jsonb')],
      'evidence': [('source_id','uuid'),('document_id','uuid'),('locator','jsonb'),('excerpt','text'),('data_value','jsonb'),('quality','varchar(50)'),('metadata','jsonb')],
      'problems': [('jurisdiction_id','uuid'),('planning_period_id','uuid'),('title','text'),('description','text'),('status','varchar(50)'),('properties','jsonb')],
      'causes': [('problem_id','uuid'),('parent_cause_id','uuid'),('label','text'),('description','text'),('evidence_strength','varchar(50)')],
      'population_groups': [('jurisdiction_id','uuid'),('name','varchar(255)'),('definition','text'),('properties','jsonb')],
      'locations': [('jurisdiction_id','uuid'),('name','varchar(255)'),('location_type','varchar(80)'),('geometry','jsonb'),('properties','jsonb')],
      'indicators': [('jurisdiction_id','uuid'),('name','text'),('unit','varchar(100)'),('definition','text'),('methodology','text'),('properties','jsonb')],
      'indicator_observations': [('indicator_id','uuid'),('reference_date','date'),('observation_type','varchar(50)'),('numeric_value','double precision'),('text_value','text'),('source_id','uuid'),('methodology','text'),('validation_status','varchar(50)')],
      'assumptions': [('jurisdiction_id','uuid'),('planning_period_id','uuid'),('statement','text'),('status','varchar(50)'),('evidence_strength','varchar(50)'),('properties','jsonb')],
      'interventions': [('jurisdiction_id','uuid'),('planning_period_id','uuid'),('name','text'),('intervention_type','varchar(80)'),('implementing_unit','varchar(255)'),('budget_amount','numeric(20,2)'),('properties','jsonb')],
      'risks': [('jurisdiction_id','uuid'),('title','text'),('category','varchar(80)'),('probability','varchar(30)'),('impact','varchar(30)'),('owner','varchar(255)'),('status','varchar(50)'),('mitigation','text'),('properties','jsonb')],
      'decisions': [('jurisdiction_id','uuid'),('issue','text'),('decision_text','text'),('decision_date','date'),('decision_maker','varchar(255)'),('status','varchar(50)'),('properties','jsonb')],
      'actions': [('decision_id','uuid'),('title','text'),('owner','varchar(255)'),('deadline','date'),('status','varchar(50)'),('evidence_refs','jsonb')],
      'milestones': [('action_id','uuid'),('title','text'),('due_date','date'),('status','varchar(50)'),('properties','jsonb')],
      'dependencies': [('source_type','varchar(80)'),('source_id','uuid'),('target_type','varchar(80)'),('target_id','uuid'),('dependency_type','varchar(80)'),('properties','jsonb')],
      'findings': [('audit_id','uuid'),('finding_type','varchar(100)'),('severity','varchar(30)'),('confidence','double precision'),('status','varchar(50)'),('claim','text'),('rationale','text'),('evidence_refs','jsonb'),('rule_refs','jsonb'),('properties','jsonb')],
      'model_calls': [('audit_id','uuid'),('provider','varchar(80)'),('model','varchar(120)'),('prompt_version','varchar(80)'),('input_payload','jsonb'),('output_payload','jsonb'),('status','varchar(50)'),('cost','numeric(20,6)'),('metadata','jsonb')],
      'research_runs': [('audit_id','uuid'),('trigger_type','varchar(100)'),('query','text'),('status','varchar(50)'),('source_snapshot_refs','jsonb'),('result','jsonb'),('confidence','double precision')],
      'scenarios': [('jurisdiction_id','uuid'),('planning_period_id','uuid'),('name','varchar(255)'),('description','text'),('assumptions','jsonb'),('parameters','jsonb'),('results','jsonb'),('status','varchar(50)')],
      'lessons_learned': [('jurisdiction_id','uuid'),('planning_period_id','uuid'),('title','text'),('lesson','text'),('evidence_refs','jsonb'),('tags','jsonb')]
    }
    for table, cols in simple_tables.items():
        parts = ['id uuid PRIMARY KEY DEFAULT gen_random_uuid()', 'created_at timestamptz NOT NULL DEFAULT now()']
        for name, typ in cols:
            default = " DEFAULT '{}'::jsonb" if typ == 'jsonb' else ''
            parts.append(f'"{name}" {typ}{default}')
        op.execute(f'CREATE TABLE "{table}" ({", ".join(parts)})')

    op.execute("INSERT INTO app_settings(key,value,updated_at) VALUES ('AI_PROVIDER','NO_AI',now()) ON CONFLICT DO NOTHING")

def downgrade():
    for t in ['lessons_learned','scenarios','research_runs','model_calls','findings','dependencies','milestones','actions','decisions','risks','interventions','assumptions','indicator_observations','indicators','locations','population_groups','causes','problems','evidence','graph_edges','graph_nodes','rule_versions','rules','sources','audit_events','audits','regulations','documents','planning_periods','jurisdictions','app_settings']:
        op.execute(f'DROP TABLE IF EXISTS "{t}" CASCADE')
