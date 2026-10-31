# Sources index

Official merit lists, prospectuses and admission regulations. **URLs only** — the PDFs themselves stay in the owner's local `sources/` folder (git-ignored), because merit PDFs contain names and CNICs (master plan 9.2).

Every number in `data/` must point to one of these via its `source_url`.

| University | Year | Document (list / prospectus / regulation) | Official URL | Fetched (YYYY-MM-DD) | Local file name | Notes |
|---|---|---|---|---|---|---|
| UET Lahore | 2025 | Minimum aggregate | https://apply.uet.edu.pk/pdf/MinimumAggregate2-1.pdf | — | — | from plan, not fetched yet |
| PU | 2025 | Merit of last year | https://pu.edu.pk/downloads/Merit-of-Last-Year-2025.pdf | — | — | from plan, not fetched yet |
| PMDC | 2026 | Admissions, Curriculum and Conduct Policy and Regulations 2026 (reg. 6(3): 50/40/10) | https://pmdc.pk/Documents/law/Notification%20PM%26DC%20%28ADMISSIONS%2C%20CURRICULUM%20AND%20CONDUCT%29%20POLICY%20AND%20REGULATIONS%202026.pdf | 2026-10-31 | pmdc2026.pdf | 12.5 MB, 28 pages |
| PMDC | 2025 | Admissions Regulations 2025 (reg. 4(3): 50/40/10) | https://pmdc.pk/Documents/law/Admissions%20Regulations-2025.pdf | 2026-10-31 | pmdc2025.pdf | 12.6 MB, 19 pages |
| PMDC | 2025 | New Syllabus for MDCAT 2025 (180 MCQs, no negative marking) | https://pmdc.pk/Documents/press/New%20Syllabus%20for%20MDCAT%202025.pdf | 2026-10-31 | — | read once, not kept |
| UHS | 2025 | Public MBBS 13th and final selection list (HTML, open merit) | https://uhs.edu.pk/13selecionlistpublicom25.php | 2026-10-31 | — | HTML table; parsed by scripts/uhs_closing.py |
| UHS | 2025 | Public MBBS 1st selection list | https://uhs.edu.pk/1selecionlistpublicom25.php | 2026-10-31 | — | HTML |
| UHS | 2025 | Public BDS 13th / 1st selection lists | https://uhs.edu.pk/13selecionlistpublicbdsom25.php , https://uhs.edu.pk/1selecionlistpublicbdsom25.php | 2026-10-31 | — | HTML |
| UHS | 2025 | Private MBBS 5th (final) / 1st selection lists | https://uhs.edu.pk/5selecionlistprivateom25.php , https://uhs.edu.pk/1selecionlistprivateom25.php | 2026-10-31 | — | HTML |
| UHS | 2025 | Private BDS 3rd / 1st selection lists | https://uhs.edu.pk/3selecionlistprivatebdsom25.php , https://uhs.edu.pk/1selecionlistprivatebdsom25.php | 2026-10-31 | — | HTML; 3rd is the latest published |
| UHS | 2024 | Public MBBS 17th (last) selection list, open merit | https://uhs.edu.pk/17thselectionlistpublic24.php (17 PDFs `admissions2024data/17<college>24.pdf`) | 2026-10-31 | 17*24.pdf | 4.4 MB total |
| UET Taxila | 2026 | Admission eligibility page (merit formula 33/50/17; BS Math/Physics 70/30) | https://admissions.uettaxila.edu.pk/Admission_Eligibility.php | 2026-10-31 | — | HTML |
| UET Taxila | 2026 | Merit / aggregate calculator (default totals: SSC 1100, test 400) | https://admissions.uettaxila.edu.pk/Calculator.php | 2026-10-31 | — | HTML |
| UET Taxila | 2025 | UG Prospectus 2025: 24.11 formula + Hafiz example; p. 136 Merit Position Entry 2024 (closing, all categories) | https://admissions.uettaxila.edu.pk/Downloads/UET-Prospectus-2025.pdf | 2026-10-31 | uett_pros25.pdf | 16 MB, 165 pages; open merit column used |
| UET Peshawar | 2026 | UG Prospectus 2026-27 (merit 10/40/50; BS CS 40/60; test 100 MCQs) | https://www.uetpeshawar.edu.pk/wp-content/uploads/2026/08/UG-Prospectus-2026-27-17-08-2026.pdf | 2026-10-31 | uetp_pros26.pdf | 18 MB; no closing merit inside |
| NED | 2026 | Admissions Schedule 2026 (MS = 40% HSC-I + 60% test) | https://www.neduet.edu.pk/sites/default/files/Admissions-2026/ADMISSIONS_SCHEDULE_2026.pdf | 2026-10-31 | ned_sched.pdf | 0.5 MB |
| NED | 2026 | Undergraduate FAQ 2026 (test 4 x 25 marks, 50% pass) | https://www.neduet.edu.pk/sites/default/files/Admissions-2026/FAQ.pdf | 2026-10-31 | ned_faq.pdf | 0.3 MB; call lists (Merit_Main.htm) are order of merit only |
| FAST-NUCES | 2026 | Eligibility Criteria page (computing 50/40/10, engineering 33/50/17) | https://nu.edu.pk/Admissions/EligibilityCriteria | 2026-10-31 | — | HTML, updated 10-Aug-2026 |
| FAST-NUCES | 2026 | Prospectus 2026: Admission Information | https://www.nu.edu.pk/Admissions/ProspectusUpload/Admission%20Information.pdf | 2026-10-31 | fast_info.pdf | 1.1 MB |
| GIKI | 2026 | Eligibility and Comparative Assessment Criteria (85% test, 15% SSC) | https://giki.edu.pk/admissions/admissions-undergraduates/eligibility-criteria/ | 2026-10-31 | — | HTML |
| NUST | 2026 | Merit Generation Criteria, UG on NET basis (75% NET, 10% SSC, 15% HSSC Part-1; A level 25% O level) | https://nust.edu.pk/admissions/undergraduates/merit-criteria-for-admission-on-net-basis/ | 2026-10-31 | — | read from owner screenshot (403 to container) |
| COMSATS | 2024 | UG Prospectus 2024-25, p. 44 (10% Matric, 40% Inter, 50% NTS; engineering NTS ≥ 33%) | https://ww2.comsats.edu.pk/ro/Prospectus/UG_Prospectus/UG-Prospectus-24-25.pdf | 2026-10-31 | cui_pros2425.pdf | 13.9 MB; latest UG prospectus on ww2 (25-26 404); no closing merit inside |
| COMSATS | 2026 | Lahore campus 'Merit List Fall 2026' page: closing aggregates, 2nd (last) merit list, 9 programs | https://lahore.comsats.edu.pk/news/merit.aspx | 2026-10-31 | cui_lhr_merit.aspx | HTML, 50 KB |
| COMSATS | 2025 | Islamabad campus 'Spring 2025 Undergraduate Closing Merits' (Admission Office), 21 programs | https://ww2.comsats.edu.pk/ciitblogs/files/Closing%20merits%20Spring%202025.pdf | 2026-10-31 | Closing-merits-Spring-2025.pdf | 13 KB, 1 page |
| PU | 2025 | Undergraduate Programs Merit of Last Year 2025 (basic criteria p. 1; closing merit BS 1st/5th semester, all campuses) | https://pu.edu.pk/downloads/Merit-of-Last-Year-2025.pdf | 2026-10-31 | pu_merit25.pdf | 0.4 MB, 6 pages; 257 records |
| PU | — | Basic Criteria Merit Calculator (1st semester: 75% (Matric/4 + Inter) + 25% test) | https://journals.pu.edu.pk/old/fee/basicadmission/calculator/ | 2026-10-31 | — | HTML/JS (redirects to http://111.68.103.26) |
| KU | 2026 | Closing percentages, entrance-test-based BS/B.Ed.(H)/BE/DPT morning + Pharm-D, Admissions 2027 (dated 19-10-2026) | https://uokadmission.edu.pk/uploads/links/closing-percentages-test-based-and-pharm-d-admissions-2027-20261019144818.pdf | 2026-10-31 | ku_closing27.pdf | 0.3 MB; 38 rows |
| KU | 2026 | Visual Studies closing final points, Admissions 2027 (dated 26-10-2026) | https://uokadmission.edu.pk/uploads/links/vst-closing-final-points-2027-20261026191124.pdf | 2026-10-31 | ku_vst27.pdf | 0.3 MB; 8 rows |
| KU | 2026 | Open merit based departments (morning) 2027, list of programmes | https://uokadmission.edu.pk/uploads/links/merit-based-admissions-2027-info-20260918195453.jpg | 2026-10-31 | — | image; no closing figures |
| IIUI | 2025 | Admission Policy (03-06-2025): UG PQM = SSC 40% + HSSC 60%; test programs PQM 40% (SSC 15, HSSC 25) + test 60% | https://www.iiu.edu.pk/wp-content/uploads/2025/06/Admission-Policy-03062025.pdf | 2026-10-31 | iiui_policy.pdf | 0.4 MB, 8 pages |
| PU | 2026 | Admission Regulations Book (p. 23: basic criteria, additional marks Hafiz-e-Quran 20, late-year deduction) | https://pu.edu.pk/downloads/Admission-Regulations-Book.pdf | 2026-10-31 | pu_regs.pdf | 2.1 MB |
| KU | 2026 | Prospectus 2027 (Appendix G: open merit BS closing K/S/P, Admissions 2026; Appendix I: test-based closing, Admissions 2026; J/K/L = Admissions 2025) | https://uokadmission.edu.pk/2027/prospectus/MP2027_1.pdf | 2026-10-31 | ku_pros27.pdf | 14.5 MB, 189 pages; appendices are images (read visually, K column only) |
| UAF | 2026 | Details/Advertisement of Undergraduate Admissions 2026-27 (Urdu; p. 1 merit = Matric 30% + Inter Part-I 30% + test 40%; p. 2 test pass marks) | https://web.uaf.edu.pk/UploadFiles/Announcements/0b3c6ddc-4e43-46ca-9809-75cd6fd09553.pdf | 2026-10-31 | uaf_adv.pdf | 12.2 MB, 3 scanned pages |
| Bahria | 2022 | Admissions Policy Edition 2 (in force from Spring 2022; 2.11.3 UG = test 60% + HSSC 30% + SSC 10%; 2.10 UG test pass 33%) | https://www.bahria.edu.pk/content/Downloads/fba5713a-0270-411c-a5c7-2ff96678e727.pdf | 2026-10-31 | bu_policy.pdf | 1.0 MB, 71 pages (owner: "search kro or download kro") |
| ITU | 2026 | Norms & Criteria page, selection criteria (updated 2026-08-12) | https://itu.edu.pk/admissions/application-process/ | 2026-10-31 | — | HTML |
| Air University | 2026 | Prepare for Admission Test page: Aggregate Merit Formulation Criteria for UG (eng 10/50/40, non-eng 15/35/50), AU-CBT composition, exemptions | https://webdata.au.edu.pk/Pages/Admission/prepare_adm_test.aspx | 2026-10-31 | — | HTML; Cloudflare blocks the container, text copied by the owner |
| Air University | 2026 | Eligibility Criteria, Bachelor programs (eng HSSC ≥ 60%, others ≥ 50%, PEC test pass 33%) | https://webdata.au.edu.pk/Pages/Admission/eligibility_criteria.aspx | 2026-10-31 | — | HTML; text copied by the owner |
| PU | 2026 | Admission notice 719: 2nd phase (vacant seats) UG 2026-27, 8-28 Sep 2026 | https://pu.edu.pk/home/admission_notice/719 | 2026-10-31 | — | HTML; data/calendar.json |
| PU | 2026 | Candidate-wise merit lists Fall 2026 (portal) | https://admissions.pu.edu.pk/admissions/applicant/meritlist | 2026-10-31 | — | names; lists can include candidates beyond seats, so no closing merit derived; Merit-of-Last-Year-2026.pdf 404 on 2026-10-31 |
| UHS | 2026 | Home page: MDCAT 2026 held 20 Sep 2026; no 2026-27 MBBS/BDS selection lists yet | https://www.uhs.edu.pk/ | 2026-10-31 | — | HTML; data/calendar.json |
| KU | 2026 | Admissions 2027 links page (schedule, closing PDFs, open-merit extension) | https://uokadmission.edu.pk/links | 2026-10-31 | — | HTML; data/calendar.json |
| UET Taxila | 2026 | Downloads page (newest prospectus = 2025) | https://admissions.uettaxila.edu.pk/Downloads.php | 2026-10-31 | — | HTML; data/calendar.json |
