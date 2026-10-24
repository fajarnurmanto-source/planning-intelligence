"""Off-the-shelf Gradio reviewer UI for synthetic PI VS2 candidate only.

Run locally (127.0.0.1). Uses built-in Gradio login. NEVER mount behind
anonymous PI port 8080 or treat these decisions as institutional approvals.
"""
from __future__ import annotations
import argparse
import os
from pathlib import Path
import gradio as gr

from .reviews import ALLOWED, validate_result
from .workflow import analyze_fixture, open_store, initialize_candidate_store, decide, review_rows, export_review


def build_app(fixture: Path, store, export_folder: Path):
    analysis = analyze_fixture(fixture)
    validate_result(analysis)
    findings = analysis['findings']
    lookup = {f['id']: f for f in findings}
    choices = [(f"{f['indicator']} ({f['year']}) — {f['status']}", f['id']) for f in findings]

    def overview():
        rows = []
        current = {r['finding_id']: r for r in review_rows(store, analysis)}
        for finding in findings:
            action = current.get(finding['id'], {})
            rows.append([finding['indicator'], finding['year'], finding['status'],
                         action.get('decision', 'PENDING'), len(finding['evidence'])])
        return rows

    def show_evidence(fid):
        if fid not in lookup:
            return 'Pilih satu temuan.'
        item = lookup[fid]
        out = [f"### {item['indicator']} — {item['year']}",
               f"**{item['status']}**", item['rationale']]
        for ev in item['evidence']:
            # Gradio renders Markdown. Escape HTML and markdown-special markup in excerpts.
            import html
            source = html.escape(ev['document_title'])
            loc = html.escape(ev['locator'])
            excerpt = html.escape(ev['excerpt'])
            out.append(f'**{source}** · `{loc}` · SourceUnit `{ev["source_unit_id"]}`\n\n> {excerpt}')
        return '\n\n'.join(out)

    def save(fid, decision, note, request: 'gr.Request'):
        actor = getattr(request, 'username', None)
        if not actor:
            raise gr.Error('LOGIN_VERIFIED_REVIEWER_REQUIRED')
        try:
            action = decide(store, analysis, fid, actor, decision, note)
        except (ValueError, PermissionError) as exc:
            raise gr.Error(str(exc)) from exc
        return f"Tercatat: {action['decision']} — {action['reviewer']}", overview()

    def download():
        paths = export_review(analysis, review_rows(store, analysis), export_folder)
        return str(paths[0]), str(paths[1])

    with gr.Blocks(title='Planning Intelligence · VS2 Reviewer Candidate') as blocks:
        gr.Markdown('# Planning Intelligence · VS2 Reviewer Workspace')
        gr.Markdown('**SIMULASI LOKAL — BUKAN TEMUAN RESMI.** Menggunakan SourceUnit dan engine VS1; keputusan tidak memberikan admission produksi.')
        grid = gr.Dataframe(headers=['Indikator', 'Tahun', 'Analisis', 'Keputusan simulasi', 'Jumlah bukti'],
                            value=overview(), interactive=False)
        choice = gr.Dropdown(choices=choices, label='Pilih temuan', value=choices[0][1] if choices else None)
        evidence = gr.Markdown(value=show_evidence(choices[0][1] if choices else None))
        choice.change(show_evidence, inputs=choice, outputs=evidence, api_name=False)
        with gr.Row():
            status = gr.Dropdown(choices=sorted(ALLOWED), label='Keputusan simulasi', value='NEEDS_EVIDENCE')
            note = gr.Textbox(label='Catatan reviewer', lines=2, max_lines=4, max_length=2000)
        send = gr.Button('Simpan keputusan (simulasi)', variant='primary')
        response = gr.Textbox(label='Hasil', interactive=False)
        send.click(save, inputs=[choice,status,note],outputs=[response,grid],api_name=False)
        gr.Markdown('### Ekspor laporan berbukti')
        export = gr.Button('Buat ekspor JSON + Word')
        json_file = gr.File(label='JSON')
        docx_file = gr.File(label='Word')
        export.click(download, outputs=[json_file,docx_file],api_name=False)
    return blocks


def main():
    parser = argparse.ArgumentParser(description='PI VS2 synthetic-only local reviewer')
    parser.add_argument('--fixture', type=Path, required=True)
    parser.add_argument('--store', type=Path, required=True)
    parser.add_argument('--out', type=Path, required=True)
    parser.add_argument('--port', type=int, default=8093)
    opts = parser.parse_args()
    username = os.getenv('PI_VS2_DEMO_USER', '')
    password = os.getenv('PI_VS2_DEMO_PASS', '')
    if not (2 <= len(username) <= 128 and len(password) >= 16):
        raise SystemExit('PI_VS2_DEMO_USER and strong PI_VS2_DEMO_PASS (>=16 characters) required')
    if not (1024 <= opts.port <= 65535):
        raise SystemExit('PORT_OUT_OF_RANGE')
    store_path = opts.store.resolve()
    if store_path.parent.exists() is False:
        store_path.parent.mkdir(parents=True)
    engine = open_store('sqlite:///' + str(store_path).replace('\\', '/'))
    initialize_candidate_store(engine)
    app = build_app(opts.fixture, engine, opts.out)
    # Gradio auth is a LOCAL candidate login, not a P5 institutional identity.
    app.launch(server_name='127.0.0.1', server_port=opts.port, share=False,
               auth=(username, password), show_error=False,
               max_file_size='10mb', inbrowser=False)


if __name__ == '__main__':
    main()
