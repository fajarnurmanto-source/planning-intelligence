"""Off-the-shelf Gradio UI: PostgreSQL canonical synthetic runs → Word review.

Only binds to loopback. Gradio login is strictly a local candidate login,
not institutional OIDC and not authorization to process real/restricted data.
"""
from __future__ import annotations
import argparse
import os
from pathlib import Path
import gradio as gr

from app.vs2.reviews import ALLOWED
from .service import (canonical_engine, review_engine, list_candidate_runs,
                      analyze_runs, submit_decision, read_review_history,
                      export_candidate_report)


def build_app(source, store, output_folder: Path):
    def require_actor(request: gr.Request) -> str:
        actor = getattr(request, 'username', None)
        if not actor:
            raise gr.Error('LOCAL_LOGIN_REQUIRED')
        return actor

    def get_runs(request: gr.Request):
        require_actor(request)
        runs = list_candidate_runs(source)
        choices = [(f"{item['title']} · {item['document_type']} · {item['run_id']}",item['run_id']) for item in runs]
        return gr.update(choices=choices, value=None), gr.update(choices=choices, value=None), f'{len(runs)} parsing run sintetis tersedia.'

    def finding_table(analysis):
        latest = {r['finding_id']:r for r in read_review_history(store,analysis)}
        return [[f['indicator'],str(f['year']),f['status'],
                 latest.get(f['id'],{}).get('decision','PENDING'),len(f['evidence'])]
                for f in analysis['findings']]

    def start(left,right,group,scope,request:gr.Request):
        require_actor(request)
        try:
            analysis = analyze_runs(source,left,right,group=group,scope=scope)
        except (ValueError, PermissionError) as exc:
            raise gr.Error(str(exc)) from exc
        choices = [(f"{f['indicator']} ({f['year']}) · {f['status']}",f['id']) for f in analysis['findings']]
        return (analysis, finding_table(analysis),
                gr.update(choices=choices,value=choices[0][1] if choices else None),
                f"Analisis selesai: {len(analysis['findings'])} temuan; SHA-256 {analysis['report_sha256']}")

    def evidence(analysis,fid,request:gr.Request):
        require_actor(request)
        if not analysis or not fid:
            return 'Pilih temuan setelah menjalankan analisis.'
        item = next((f for f in analysis['findings'] if f['id']==fid),None)
        if item is None: return 'Temuan tidak ditemukan dalam analisis aktif.'
        lines = [f"{item['indicator']} — {item['year']}", item['status'],item['rationale']]
        for ev in item['evidence']:
            lines += ['',f"Dokumen: {ev['document_title']}",f"Locator: {ev['locator']}",
                      f"SourceUnit: {ev['source_unit_id']}",f"SHA: {ev['unit_fingerprint']}",
                      f"Kutipan: {ev['excerpt']}"]
        # Plain textbox; no HTML/Markdown execution from document text.
        return '\n'.join(lines)

    def decide(analysis,fid,decision,note,request:gr.Request):
        actor=require_actor(request)
        if not analysis:
            raise gr.Error('ANALYSIS_REQUIRED')
        try:
            action=submit_decision(store,analysis,fid,actor,decision,note)
        except (ValueError,PermissionError) as exc:
            raise gr.Error(str(exc)) from exc
        return f"Tersimpan: {action['decision']} / {action['reviewer']}",finding_table(analysis)

    def download(analysis,request:gr.Request):
        require_actor(request)
        if not analysis:raise gr.Error('ANALYSIS_REQUIRED')
        paths=export_candidate_report(store,analysis,output_folder)
        return str(paths[0]),str(paths[1])

    with gr.Blocks(title='PI VS3 • Canonical Review') as app:
        gr.Markdown('# Planning Intelligence · Canonical Reviewer VS3')
        gr.Markdown('**KANDIDAT SINTETIS SAJA.** Membaca ParsingRun/SourceUnit PostgreSQL PI; belum ada kewenangan review produksi.')
        active=gr.State(value=None)
        with gr.Row():
            left=gr.Dropdown(label='ParsingRun kiri (sintetis)',choices=[])
            right=gr.Dropdown(label='ParsingRun kanan (sintetis)',choices=[])
        refresh=gr.Button('Muat daftar parsing run sintetis')
        message=gr.Textbox(label='Status',interactive=False)
        refresh.click(get_runs,outputs=[left,right,message],api_name=False)
        with gr.Row():
            group=gr.Textbox(label='Kelompok indikator',value='Sasaran strategis')
            scope=gr.Textbox(label='Lingkup perbandingan',value='Perbandingan target tahunan')
        go=gr.Button('Analisis koherensi',variant='primary')
        table=gr.Dataframe(headers=['Indikator','Tahun','Analisis','Keputusan','Jumlah bukti'], interactive=False)
        finding=gr.Dropdown(label='Temuan',choices=[])
        proof=gr.Textbox(label='Bukti sumber',interactive=False,lines=12,max_lines=20)
        go.click(start,inputs=[left,right,group,scope],outputs=[active,table,finding,message],api_name=False)
        finding.change(evidence,inputs=[active,finding],outputs=proof,api_name=False)
        with gr.Row():
            decision=gr.Dropdown(label='Keputusan kandidat',choices=sorted(ALLOWED),value='NEEDS_EVIDENCE')
            note=gr.Textbox(label='Catatan',max_length=2000,lines=2)
        save=gr.Button('Simpan review kandidat')
        result=gr.Textbox(label='Hasil simpan',interactive=False)
        save.click(decide,inputs=[active,finding,decision,note],outputs=[result,table],api_name=False)
        export=gr.Button('Ekspor laporan JSON + Word')
        json_file=gr.File(label='JSON review')
        word_file=gr.File(label='Word review')
        export.click(download,inputs=[active],outputs=[json_file,word_file],api_name=False)
    return app


def main():
    parser=argparse.ArgumentParser(description='PI VS3 synthetic canonical reviewer')
    parser.add_argument('--port',type=int,default=8094)
    args=parser.parse_args()
    user=os.environ.get('PI_VS3_DEMO_USER','')
    password=os.environ.get('PI_VS3_DEMO_PASS','')
    db_url=os.environ.get('PI_VS3_CANONICAL_DATABASE_URL','')
    review_path=os.environ.get('PI_VS3_REVIEW_SQLITE_PATH','')
    output_path=os.environ.get('PI_VS3_OUTPUT_DIR','')
    if not (len(user)>=2 and len(password)>=16 and review_path and output_path):
        raise SystemExit('LOCAL_DEMO_USER_PASS_AND_SEPARATE_PATHS_REQUIRED')
    if not 1024<=args.port<=65535 or args.port in range(8080,8087):
        raise SystemExit('PORT_DISALLOWED')
    review_db=Path(review_path).resolve()
    out=Path(output_path).resolve()
    project_root=Path(__file__).resolve().parents[3]
    if (review_db.is_relative_to(project_root) or out.is_relative_to(project_root)
            or review_db.parent == Path.cwd() or out == Path.cwd()):
        raise SystemExit('CANDIDATE_STORAGE_MUST_BE_OUTSIDE_REPO')
    review_db.parent.mkdir(parents=True,exist_ok=True)
    out.mkdir(parents=True,exist_ok=True)
    source=canonical_engine(db_url)
    store=review_engine('sqlite:///'+review_db.as_posix())
    app=build_app(source,store,out)
    app.launch(server_name='127.0.0.1',server_port=args.port,
               auth=(user,password),share=False,show_error=False,
               max_file_size='10mb',inbrowser=False)

if __name__=='__main__': main()
