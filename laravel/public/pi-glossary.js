/* PI-PXT-03: presentation-only controlled glossary. Never admit evidence. */
(() => {
 'use strict'
  const statusLabels=Object.freeze({
    REVIEW_READY_WITH_ABSTENTIONS:'Siap ditelaah · beberapa bukti belum cukup',
    MIXED_VERIFIED_SOURCE_AND_CANDIDATE_RELATION:'Sumber ditemukan · hubungan perlu pemeriksaan',
    CANDIDATE:'Kandidat · belum disahkan',
    CANDIDATE_REQUIRES_LEGAL_REVIEW:'Hubungan perlu pemeriksaan hukum',
    DOCUMENTARY_SEQUENCE_REVIEW_REQUIRED:'Urutan dokumen perlu diperiksa',
    OUTPUT_TRACE_CANDIDATE_NUMERIC_BLOCKED:'Jejak keluaran ditemukan · angka belum boleh dibandingkan',
    TRACE_AVAILABLE_DECISION_ABSTAIN:'Jejak tersedia · keputusan belum dapat diambil',
    VERIFIED:'Sumber telah diperiksa',
    VERIFIED_SOURCE:'Sumber telah diperiksa',
    VERIFIED_SOURCE_BYTES:'Berkas cocok dengan arsip · keabsahan hukum belum dinilai',
    SOURCE_LOCATED_NOT_HASH_PINNED:'Lokasi sumber ditemukan · berkas belum diverifikasi',
    LOCAL_QA_OBSERVATION_NOT_SOURCE_PINNED:'Catatan uji lokal · belum menjadi bukti dokumen',
    NOT_ASSESSED:'Belum dinilai',
    BLOCKED:'Belum diizinkan',
    NO_ACTIVE_DECISION:'Belum ada keputusan pemeriksa',
    DENIED:'Tidak diizinkan',
    HIGH:'Tinggi',MEDIUM:'Sedang',LOW:'Rendah'
  })
  const nodeLabels=Object.freeze({"RegulationVersion":"Versi regulasi","Provision":"Ketentuan pasal","Authority":"Kewenangan","GovernmentAffair":"Urusan pemerintahan","PlanningArtifactVersion":"Dokumen perencanaan","ProcessStage":"Tahap proses","ProcessEvent":"Peristiwa proses","PlanningProgram":"Program","Indicator":"Indikator","BudgetArtifact":"Dokumen anggaran","EvidenceLocator":"Lokasi bukti","ReviewDecision":"Keputusan pemeriksa"})
  const relationLabels=Object.freeze({"IMPLEMENTS_PROVISION_OF_CANDIDATE":"Diduga melaksanakan ketentuan","INFORMS_MUNICIPAL_AUTHORITY_CANDIDATE":"Rujukan kewenangan kota","ISSUES_ARTIFACT":"Menerbitkan dokumen","ADMINISTRATIVE_RESPONSIBILITY_CANDIDATE":"Rujukan tanggung jawab administratif","CLASSIFIES_PROGRAM_CANDIDATE":"Kandidat klasifikasi program","GUIDES_ANNUAL_PLAN_CANDIDATE":"Rujukan rencana tahunan","CONTAINS_PROVISION":"Memuat ketentuan","GUIDES_AGENCY_STRATEGIC_PLAN_CANDIDATE":"Rujukan Renstra perangkat daerah","GUIDES_AGENCY_ANNUAL_PLAN_CANDIDATE":"Rujukan Renja perangkat daerah","STRATEGIC_PLAN_CONTAINS_PROGRAM_CANDIDATE":"Kandidat program dalam Renstra","ANNUAL_PLAN_CONTAINS_PROGRAM_CANDIDATE":"Kandidat program dalam Renja","MEASURED_BY_CANDIDATE":"Kandidat indikator pengukuran","BUDGETED_IN_CANDIDATE":"Kandidat penganggaran","RKPD_TO_BUDGET_PROCESS_GUIDANCE":"Rujukan proses RKPD ke anggaran","MATERIALIZES_ANNUAL_PLANNING_STAGE":"Dokumen tahap perencanaan tahunan","PRECEDES_DOCUMENTARY_STAGE":"Urutan tahap dokumen","ESTABLISHES_VERSION":"Menetapkan versi dokumen","AVAILABLE_FOR_HUMAN_REVIEW_NO_DECISION":"Tersedia untuk telaah, belum diputuskan"})

  const roles=Object.freeze({institution_admin:'Administrator institusi',reviewer:'Pemeriksa',analyst:'Analis',viewer:'Pembaca',admin:'Administrator'})
  const lookup=(map,key,fallback)=>Object.prototype.hasOwnProperty.call(map,key)?map[key]:fallback
  window.PIGlossary=Object.freeze({
    status:code=>lookup(statusLabels,code,'Perlu pemeriksaan lanjutan'),
    node:code=>lookup(nodeLabels,code,'Jenis bukti perlu diperiksa'),
    relation:code=>lookup(relationLabels,code,'Hubungan perlu diperiksa'),
    role:code=>lookup(roles,code,'Akun lokal')
  })
})()
