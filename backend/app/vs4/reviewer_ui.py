"""Gradio's prebuilt login, tables, selectors and downloads: no UI framework rewrite.

Loopback synthetic candidate only, not an institutional identity provider.
"""
from __future__ import annotations
import argparse
import json
import os
from pathlib import Path
import tempfile
import shutil
import gradio as gr
from app.vs2.reviews import ALLOWED
from app.vs3.service import canonical_engine
from . import service
from app.vs5.intake import analyze_and_archive
from app.graph_core.intake import analyze_and_archive_four
from app.graph_g2.intake import analyze_g1_archive_and_ledger
from app.graph_g3.intake import analyze_g2_archive_and_originals
from app.graph_g4.intake import analyze_archived_g2_g3, synthetic_g4_example
from app.graph_g5.intake import analyze_archived_g4, synthetic_g5_example



def _stage_reports_for_gradio(paths, output: Path) -> tuple[str, str]:
    """Copy only this export's Word/JSON into Gradio's allowed OS temp path.

    Do not expose the entire persistent archive with allowed_paths.
    """
    base = Path(output).resolve(strict=True)
    items = [Path(p).resolve(strict=True) for p in paths]
    if (len(items) != 2 or {p.suffix.lower() for p in items} != {'.json', '.docx'}
            or any(not p.is_file() or not p.is_relative_to(base) for p in items)):
        raise ValueError('INVALID_CANDIDATE_REPORT_PATHS')
    tmp = Path(tempfile.mkdtemp(prefix='pi-gradio-export-'))
    results = []
    for source_file in items:
        candidate = tmp / source_file.name
        shutil.copyfile(source_file, candidate)
        results.append(str(candidate))
    return tuple(results)

def build_app(store, output: Path, roles: dict[str,str], *, source=None, fixture: Path | None=None):
    def actor(request: gr.Request, *, write=False):
        username=getattr(request,'username',None)
        try:
            return service.require_role(username,roles,write=write)
        except PermissionError as exc:
            raise gr.Error(str(exc)) from exc

    def refresh(request: gr.Request):
        user=actor(request)
        entries=service.history(store,actor=user,roles=roles)
        choices=[(e['digest'][:12]+'  ·  '+e['created_by']+'  ·  '+e['created_at_utc'],e['digest']) for e in entries]
        return gr.update(choices=choices,value=None),f'{len(entries)} analisis tersimpan.'

    def restore(digest: str,request: gr.Request):
        user=actor(request)
        if not digest:
            return None, [], gr.update(choices=[],value=None), 'Pilih arsip pada daftar, lalu klik Buka analisis.'
        analysis=service.resume(store,digest,actor=user,roles=roles)
        choices=[(f"{f['indicator']} ({f['year']})",f['id']) for f in analysis['findings']]
        return (digest,service.table(store,digest,actor=user,roles=roles),
                gr.update(choices=choices,value=choices[0][1] if choices else None),
                'Arsip dibuka: '+digest[:16])

    def present(analysis:dict, user:str):
        digest=analysis['report_sha256']
        findings=analysis['findings']
        choices=[(f"{f['indicator']} ({f['year']})",f['id']) for f in findings]
        return (digest,service.table(store,digest,actor=user,roles=roles),
                gr.update(choices=choices,value=choices[0][1] if choices else None),
                f"Analisis {digest[:16]} tersimpan. {len(findings)} temuan.")

    def demo(request:gr.Request):
        user=actor(request)
        if fixture is None:
            raise gr.Error('DEMO_FIXTURE_UNAVAILABLE')
        return present(service.from_demo(store,fixture,user,roles),user)

    def upload_pair(left_file,right_file,group,scope,request:gr.Request):
        user=actor(request)
        if not left_file or not right_file:
            raise gr.Error('PILIH_DUA_BERKAS_SINTETIS')
        try:
            analysis=analyze_and_archive(store,left_file,right_file,group=group,
                                         scope=scope,actor=user,roles=roles)
        except (ValueError, PermissionError, OSError) as exc:
            raise gr.Error(str(exc)) from exc
        return present(analysis,user)

    def session_identity(request: gr.Request):
        user = actor(request)
        role = roles[user]
        message = f'Sesi aktif: {user} | Peran: {role}.'
        if role != 'reviewer':
            message += ' Viewer tidak dapat menyimpan keputusan. Login ulang sebagai local-reviewer untuk review.'
        return message

    def upload_four(a, b, c, d, group, scope, request: gr.Request):
        user = actor(request)
        paths = {'RPJMD':a, 'RKPD':b, 'RENSTRA':c, 'RENJA':d}
        if any(not p for p in paths.values()):
            raise gr.Error('PILIH_KEEMPAT_DOKUMEN_SINTETIS')
        try:
            analysis = analyze_and_archive_four(store, paths, group=group, scope=scope,
                                                actor=user, roles=roles)
        except (ValueError, PermissionError, OSError) as exc:
            raise gr.Error(str(exc)) from exc
        response = present(analysis, user)
        graph = analysis['graph_metrics']
        explanation = ('Graph: {nodes} node, {edges} edge, '
                       '{compared_observation_pairs} pasangan indikator, '
                       '{findings} temuan. Hierarki hanya hipotesis struktural; '
                       'tidak membuktikan hubungan sebab-akibat atau kepatuhan hukum.').format(**graph)
        return (*response, explanation)

    def run_g2(g1_digest, claim_file, request: gr.Request):
        user=actor(request)
        if not g1_digest or not claim_file:
            raise gr.Error('PILIH_ARSIP_GRAPH_G1_DAN_LEDGER_SINTETIS')
        try:
            analysis=analyze_g1_archive_and_ledger(store,g1_digest,claim_file,
                                                    actor=user,roles=roles)
        except (ValueError,LookupError,PermissionError,OSError) as exc:
            raise gr.Error(str(exc)) from exc
        response=present(analysis,user)
        m=analysis['graph_metrics']
        return (*response,(f"G2: {m['nodes']} entitas, {m['edges']} relasi eksplisit, "
                f"{m['evidence_link_declarations']} deklarasi dengan locator, "
                f"{m['findings']} temuan/gap. Ini BUKAN bukti kausalitas."))

    def run_g3(g2_digest, claim_file, original_rpjmd, original_rkpd,
               original_renstra, original_renja, request:gr.Request):
        user=actor(request)
        if not g2_digest or not claim_file or any(not x for x in
                [original_rpjmd, original_rkpd,original_renstra, original_renja]):
            raise gr.Error('PILIH_ARSIP_G2_LEDGER_DAN_4_DOKUMEN_ASLI_YANG_SAMA')
        files={'RPJMD':original_rpjmd,'RKPD':original_rkpd,
               'RENSTRA':original_renstra,'RENJA':original_renja}
        try:
            report=analyze_g2_archive_and_originals(store,g2_digest,claim_file,files,
                                                      actor=user,roles=roles)
        except (ValueError, LookupError,PermissionError,OSError) as exc:
            raise gr.Error(str(exc)) from exc
        response=present(report,user)
        m=report['verification_metrics']
        msg=(f"G3: {m['node_quotes_verified']}/{m['node_quotes_total']} kutipan entitas, "
             f"{m['edge_quotes_verified']}/{m['edge_quotes_total']} kutipan relasi cocok "
             f"di SourceUnit. {m['source_gaps']} gap. "
             'KECOCOKAN TEKS BUKAN PEMBUKTIAN HUBUNGAN KAUSAL/LEGAL.')
        return (*response,msg)

    def run_g4(g3_digest,request:gr.Request):
        user=actor(request)
        if not g3_digest:
            raise gr.Error('PILIH_ARSIP_G3_UNTUK_G4')
        try:
            report=analyze_archived_g2_g3(store,g3_digest,actor=user,roles=roles)
        except (ValueError, LookupError, PermissionError, OSError) as exc:
            raise gr.Error(str(exc)) from exc
        response=present(report,user)
        m=report['assessment_metrics']
        return (*response,(f"G4: {m['edges']} relasi dinilai secara leksikal; "
                f"{m['status_counts']}; {m['review_required']} memerlukan review. "
                'Kutipan eksplisit tidak membuktikan kausalitas atau kesahihan kebijakan.'))

    def g4_demo(request:gr.Request):
        user=actor(request)
        folder=os.environ.get('PI_G4_SYNTHETIC_SAMPLES_DIR','')
        if not folder:raise gr.Error('G4_LOCAL_SAMPLE_DIR_NOT_CONFIGURED')
        try:
            report=synthetic_g4_example(store,folder,actor=user,roles=roles)
        except (ValueError,LookupError,PermissionError,OSError) as exc:
            raise gr.Error(str(exc)) from exc
        response=present(report,user)
        m=report['assessment_metrics']
        return (*response, f"Contoh sintetis G4: {m['status_counts']}; seluruh relasi perlu review.")

    def run_g5(g4_digest, request:gr.Request):
        user=actor(request)
        if not g4_digest:
            raise gr.Error('PILIH_ARSIP_G4_UNTUK_G5')
        try:
            report=analyze_archived_g4(store,g4_digest,actor=user,roles=roles)
        except (ValueError,LookupError,PermissionError,OSError) as exc:
            raise gr.Error(str(exc)) from exc
        response=present(report,user)
        metrics=report['assessment_metrics']
        return (*response,(f"G5: {metrics['edges']} relasi: {metrics['status_counts']}; "
                f"{metrics['high_priority']} prioritas tinggi. "
                'Periksa arah, negasi dan modalitas; bukan validasi kausalitas.'))

    def g5_demo(request:gr.Request):
        user=actor(request)
        folder=os.environ.get('PI_G4_SYNTHETIC_SAMPLES_DIR','')
        if not folder:raise gr.Error('G5_LOCAL_SAMPLE_DIR_NOT_CONFIGURED')
        try:
            report=synthetic_g5_example(store,folder,actor=user,roles=roles)
        except (ValueError,LookupError,PermissionError,OSError) as exc:
            raise gr.Error(str(exc)) from exc
        response=present(report,user)
        m=report['assessment_metrics']
        return (*response,(f"G5 sintetis: {m['status_counts']}; "
                           f"{m['high_priority']} prioritas tinggi; semua memerlukan review."))

    def source_runs(request:gr.Request):
        actor(request)
        if source is None:raise gr.Error('POSTGRESQL_SOURCE_NOT_CONFIGURED')
        runs=service.list_candidate_runs(source)
        choices=[(f"{r['title']} · {r['document_type']} · {r['run_id']}",r['run_id']) for r in runs]
        return gr.update(choices=choices,value=None),gr.update(choices=choices,value=None)

    def compare(left,right,group,scope,request:gr.Request):
        user=actor(request)
        if source is None:raise gr.Error('POSTGRESQL_SOURCE_NOT_CONFIGURED')
        try:
            analysis=service.from_database(source,store,left,right,group=group,scope=scope,actor=user,roles=roles)
        except (ValueError,PermissionError) as exc:
            raise gr.Error(str(exc)) from exc
        return present(analysis,user)

    def show_proof(digest,fid,request:gr.Request):
        user=actor(request)
        if not digest or not fid:return 'Pilih temuan.'
        try:return service.proof(store,digest,fid,actor=user,roles=roles)
        except (ValueError,LookupError,PermissionError) as exc:raise gr.Error(str(exc)) from exc

    def save(digest,fid,selected,note,request:gr.Request):
        user=actor(request,write=True)
        if not digest or not fid:raise gr.Error('PILIH_ANALISIS_DAN_TEMUAN')
        try:
            event=service.decision(store,digest,fid,user,roles,selected,note)
        except (ValueError,LookupError,PermissionError) as exc:
            raise gr.Error(str(exc)) from exc
        return 'Aksi tersimpan: '+event['event_id'],service.table(store,digest,actor=user,roles=roles)

    def make_report(digest,request:gr.Request):
        user=actor(request)
        if not digest:raise gr.Error('PILIH_ANALISIS')
        try:paths=service.export(store,digest,output,user,roles)
        except (ValueError,LookupError,PermissionError) as exc:raise gr.Error(str(exc)) from exc
        return _stage_reports_for_gradio(paths, output)

    with gr.Blocks(title='Planning Intelligence · Review VS4 · Intake VS5 · Graph G1 / G2 / G3 / G4 / G5') as app:
        gr.Markdown('# Planning Intelligence · Evidence Review + Planning Knowledge Graph G1–G5')
        gr.Markdown('**SINTETIS / NON-PRODUKSI.** Analisis tersimpan dapat dibuka kembali; keputusan reviewer dicatat sebagai event dan laporan dapat diekspor.')
        with gr.Row():
            whoami=gr.Button('Cek akun dan peran sesi',scale=1)
            identity=gr.Textbox(label='Akses saat ini',interactive=False,scale=3)
        whoami.click(session_identity,outputs=identity,api_name=False)
        current=gr.State(value=None)
        with gr.Row():
            archived=gr.Dropdown(label='Arsip analisis',choices=[],scale=4)
            update=gr.Button('Muat arsip',scale=1)
            reopen=gr.Button('Buka analisis',scale=1)
        status=gr.Textbox(label='Status',interactive=False)
        update.click(refresh,outputs=[archived,status],api_name=False)
        with gr.Accordion('Buat analisis baru',open=True):
            if fixture is not None:
                gr.Markdown('**Demo tanpa PostgreSQL:** gunakan fixture kanonis VS1 yang disertakan di repository.')
                demobutton=gr.Button('Jalankan demo sintetis',variant='primary')
            if source is not None:
                loadruns=gr.Button('Muat ParsingRun PostgreSQL (sintetis)')
                with gr.Row():
                    left=gr.Dropdown(label='ParsingRun kiri',choices=[])
                    right=gr.Dropdown(label='ParsingRun kanan',choices=[])
                loadruns.click(source_runs,outputs=[left,right],api_name=False)
                with gr.Row():
                    group=gr.Textbox(label='Kelompok indikator',value='Sasaran strategis')
                    scope=gr.Textbox(label='Lingkup analisis',value='Target tahunan')
                execute=gr.Button('Analisis PostgreSQL',variant='primary')
        with gr.Accordion('Bandingkan dua dokumen sintetis · PDF/DOCX/XLSX',open=True):
            gr.Markdown('Dokumen harus dibuat khusus pengujian dan mengandung marker **PI_SYNTHETIC_FIXTURE_ONLY**. Gunakan dua contoh XLSX dari paket VS5. Tidak untuk dokumen resmi atau produksi.')
            with gr.Row():
                file_left=gr.File(label='Dokumen sumber A (sintetis)',file_types=['.pdf','.docx','.xlsx'],type='filepath')
                file_right=gr.File(label='Dokumen sumber B (sintetis)',file_types=['.pdf','.docx','.xlsx'],type='filepath')
            with gr.Row():
                file_group=gr.Textbox(label='Kelompok perbandingan',value='Sasaran strategis')
                file_scope=gr.Textbox(label='Lingkup',value='Target tahunan')
            compare_files=gr.Button('Analisis dua dokumen dan simpan',variant='primary')
        with gr.Accordion('Planning Knowledge Graph · 4 dokumen uji', open=True):
            gr.Markdown('**SINTETIS ONLY.** Pilih jenis dokumen sesuai kolom; relasi RPJMD→RKPD, '
                        'RPJMD→Renstra, Renstra→Renja hanyalah hipotesis struktur untuk pre-review. '
                        'Semua dokumen harus memuat marker PI_SYNTHETIC_FIXTURE_ONLY.')
            with gr.Row():
                graph_rpjmd=gr.File(label='RPJMD (sintetis)',file_types=['.pdf','.docx','.xlsx'],type='filepath')
                graph_rkpd=gr.File(label='RKPD (sintetis)',file_types=['.pdf','.docx','.xlsx'],type='filepath')
            with gr.Row():
                graph_renstra=gr.File(label='Renstra (sintetis)',file_types=['.pdf','.docx','.xlsx'],type='filepath')
                graph_renja=gr.File(label='Renja (sintetis)',file_types=['.pdf','.docx','.xlsx'],type='filepath')
            with gr.Row():
                graph_group=gr.Textbox(label='Kelompok indikator yang dinyatakan',value='Sasaran strategis')
                graph_scope=gr.Textbox(label='Lingkup yang dinyatakan',value='Target tahunan')
            graphbutton=gr.Button('Bangun graph dan periksa koherensi 4 dokumen',variant='primary')
            graph_summary=gr.Textbox(label='Ringkasan graph',interactive=False)
        with gr.Accordion('G2 · Hubungan substantif program–sasaran (klaim eksplisit)',open=True):
            gr.Markdown('Pilih dahulu arsip analisis **G1** yang sudah berjalan. '
                        'Unggah *G2_CLAIM_LEDGER_SYNTHETIC.xlsx* berisi entitas dan relasi yang '
                        'dinamai secara eksplisit. Kutipan dalam ledger adalah deklarasi penguji, '
                        '**bukan** bukti kausalitas atau verifikasi teks dokumen asli.')
            g2_claim=gr.File(label='Ledger relasi G2 (XLSX sintetis)',file_types=['.xlsx'],type='filepath')
            g2_execute=gr.Button('Analisis graph substantif dari arsip G1',variant='primary')
            g2_summary=gr.Textbox(label='Ringkasan G2',interactive=False)
        with gr.Accordion('G3 · Periksa kutipan pada dokumen asli SourceUnit',open=True):
            gr.Markdown('Pilih arsip **G2** (bukan G1), ledger yang sama, dan empat **dokumen asli '
                        'identik byte-per-byte** dengan yang digunakan saat G1. '
                        'G3 hanya mengonfirmasi keberadaan teks pada locator parser; **BUKAN** '
                        'bukti relevansi semantik, efektivitas, kausalitas, atau kesesuaian hukum.')
            g3_ledger=gr.File(label='Ledger G2 asli untuk verifikasi G3',file_types=['.xlsx'],type='filepath')
            with gr.Row():
                g3_rpjmd=gr.File(label='RPJMD original',file_types=['.pdf','.docx','.xlsx'],type='filepath')
                g3_rkpd=gr.File(label='RKPD original',file_types=['.pdf','.docx','.xlsx'],type='filepath')
            with gr.Row():
                g3_renstra=gr.File(label='Renstra original',file_types=['.pdf','.docx','.xlsx'],type='filepath')
                g3_renja=gr.File(label='Renja original',file_types=['.pdf','.docx','.xlsx'],type='filepath')
            g3_execute=gr.Button('Periksa kutipan berdasarkan SourceUnit (G3)',variant='primary')
            g3_summary=gr.Textbox(label='Hasil verifikasi sumber G3',interactive=False)
        with gr.Accordion('G4 · Penilaian eksplisit dukungan atau sangkalan (arsip G3)',open=True):
            gr.Markdown('Pilih **arsip G3** pada daftar arsip lalu **Buka analisis**. '
                        'Klik tombol ini tanpa mengunggah ulang dokumen. '
                        'Pemeriksaan deterministik hanya mengecek penyebutan dua entitas, '
                        'frasa relasi dan negasi dalam kutipan yang sudah terlokasi oleh G3. '
                        'Ini **bukan** semantic entailment atau pembuktian kausalitas.')
            g4_execute=gr.Button('Nilai kekuatan pernyataan relasi dari arsip G3',variant='primary')
            g4_demo_button=gr.Button('Jalankan satu contoh G1–G4 (tanpa unggah ulang)')
            g4_summary=gr.Textbox(label='Ringkasan penilaian bukti G4',interactive=False)
        with gr.Accordion('G5 · Audit arah, negasi dan modalitas relasi (arsip G4)',open=True):
            gr.Markdown('Gunakan arsip **G4** yang telah disimpan. G5 menguji urutan '
                        'subjek–predikat–objek, pernyataan yang baru berupa rencana, '
                        'sangkalan serta gap bukti. Ini triase eksplisit, **bukan** inferensi semantik atau kausal.')
            g5_execute=gr.Button('Triase relasi G5 dari arsip G4',variant='primary')
            g5_demo_button=gr.Button('Jalankan satu contoh G1–G5 (tanpa unggah ulang)')
            g5_summary=gr.Textbox(label='Ringkasan triase G5',interactive=False)
        finding_table=gr.Dataframe(headers=['Indikator','Tahun','Status','Review','Bukti'],interactive=False)
        selected_finding=gr.Dropdown(label='Pilih temuan',choices=[])
        evidence=gr.Textbox(label='Bukti & locator sumber',interactive=False,lines=10,max_lines=22)
        reopen.click(restore,inputs=archived,outputs=[current,finding_table,selected_finding,status],api_name=False)
        compare_files.click(upload_pair,inputs=[file_left,file_right,file_group,file_scope],outputs=[current,finding_table,selected_finding,status],api_name=False)
        if fixture is not None:
            demobutton.click(demo,outputs=[current,finding_table,selected_finding,status],api_name=False)
        if source is not None:
            execute.click(compare,inputs=[left,right,group,scope],outputs=[current,finding_table,selected_finding,status],api_name=False)
        graphbutton.click(upload_four,
                          inputs=[graph_rpjmd,graph_rkpd,graph_renstra,graph_renja,graph_group,graph_scope],
                          outputs=[current,finding_table,selected_finding,status,graph_summary],api_name=False)
        g2_execute.click(run_g2,inputs=[current,g2_claim],
                          outputs=[current,finding_table,selected_finding,status,g2_summary],api_name=False)
        g3_execute.click(run_g3,inputs=[current,g3_ledger,g3_rpjmd,g3_rkpd,g3_renstra,g3_renja],
                         outputs=[current,finding_table,selected_finding,status,g3_summary],api_name=False)
        g4_demo_button.click(g4_demo,
                             outputs=[current,finding_table,selected_finding,status,g4_summary],api_name=False)
        g4_execute.click(run_g4,inputs=[current],
                         outputs=[current,finding_table,selected_finding,status,g4_summary],api_name=False)
        g5_execute.click(run_g5,inputs=[current],
                         outputs=[current,finding_table,selected_finding,status,g5_summary],api_name=False)
        g5_demo_button.click(g5_demo,
                             outputs=[current,finding_table,selected_finding,status,g5_summary],api_name=False)
        selected_finding.change(show_proof,inputs=[current,selected_finding],outputs=evidence,api_name=False)
        with gr.Row():
            selected_decision=gr.Dropdown(label='Keputusan simulasi',choices=sorted(ALLOWED),value='NEEDS_EVIDENCE')
            note=gr.Textbox(label='Catatan reviewer',lines=2,max_length=2000)
        submit=gr.Button('Simpan keputusan reviewer')
        feedback=gr.Textbox(label='Hasil simpan',interactive=False)
        submit.click(save,inputs=[current,selected_finding,selected_decision,note],outputs=[feedback,finding_table],api_name=False)
        export_button=gr.Button('Ekspor laporan Word + JSON')
        with gr.Row():
            json_file=gr.File(label='JSON')
            word_file=gr.File(label='Word')
        export_button.click(make_report,inputs=current,outputs=[json_file,word_file],api_name=False)
    return app


def main():
    cli=argparse.ArgumentParser()
    cli.add_argument('--port',type=int,default=8095)
    args=cli.parse_args()
    if args.port in range(8080,8087) or not 1024<=args.port<=65535:
        raise SystemExit('PORT_NOT_ALLOWED')
    raw_auth=os.environ.get('PI_VS4_LOCAL_AUTH_JSON','')
    raw_roles=os.environ.get('PI_VS4_ROLES_JSON','')
    if not raw_auth or not raw_roles:raise SystemExit('LOCAL_AUTH_AND_ROLES_REQUIRED')
    users=json.loads(raw_auth)
    roles=json.loads(raw_roles)
    if (not isinstance(users,dict) or not isinstance(roles,dict) or not users or
        set(users)!=set(roles) or
        any(not isinstance(k,str) or len(k)<2 or
            not isinstance(v,str) or len(v)<16 for k,v in users.items()) or
        any(x not in ('viewer','reviewer') for x in roles.values())):
        raise SystemExit('INVALID_LOCAL_USER_AND_ROLE_CONFIG')
    db_file=os.environ.get('PI_VS4_REVIEW_SQLITE_PATH','')
    export_dir=os.environ.get('PI_VS4_OUTPUT_DIR','')
    if not db_file or not export_dir:raise SystemExit('SEPARATE_REVIEW_STORE_AND_EXPORT_PATH_REQUIRED')
    db_file=Path(db_file).resolve()
    export_dir=Path(export_dir).resolve()
    root=Path(__file__).resolve().parents[3]
    if (db_file.is_relative_to(root) or export_dir.is_relative_to(root)
            or db_file.parent == Path.cwd() or export_dir==Path.cwd()):
        raise SystemExit('CANDIDATE_FILES_MUST_BE_OUTSIDE_REPOSITORY')
    db_file.parent.mkdir(parents=True,exist_ok=True)
    export_dir.mkdir(parents=True,exist_ok=True)
    source_url=os.environ.get('PI_VS4_CANONICAL_DATABASE_URL','')
    source=canonical_engine(source_url) if source_url else None
    store=service.build_candidate_store('sqlite:///'+db_file.as_posix(),canonical_database_url=source_url)
    fixture=root / 'backend' / 'tests' / 'fixtures' / 'pi_vs1_canonical_pair.json'
    app=build_app(store,export_dir,roles,source=source,fixture=fixture if fixture.exists() else None)
    app.launch(server_name='127.0.0.1',server_port=args.port,auth=list(users.items()),
               share=False,inbrowser=False,show_error=False,max_file_size='10mb',enable_monitoring=False)

if __name__=='__main__':main()
