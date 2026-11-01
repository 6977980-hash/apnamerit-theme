# Changelog

## 0.28.0 (2026-11-01)
New `/guides/` hub (CollectionPage, `[am_hub type="guides"]` lists every `/guides/` page plus `/mdcat-passing-marks/` and `/uhs-mbbs-admission/`). Header: "Guides" replaces "Methodology" (desktop only, hidden under 480 px; Methodology stays in the footer). Footer Tools column links Guides. Guide pages now have `/guides/` as breadcrumb parent.

## 0.27.0 (2026-11-01)
New logo and a redesigned header and footer. Logo mark = letter A on the green tile with a gold "top merit" dot, inline SVG (no request) via `am_logo_mark()` / `am_brand()`; same drawing in `assets/icons/icon.svg`, `icon-48.png`, `icon-180.png`, `favicon.ico` and new `logo-512.png` (Organization logo in JSON-LD). Header: accent top bar, wordmark with tagline on desktop and stacked wordmark on mobile, pill nav with the Predictor as a button (`"cta": true` in `data/nav.json`); one line from 360 px. Footer: dark green, brand column + Tools / Data / Site columns (`"col"` in `data/nav.json` footer items), disclaimer bar. `am.css` comments removed to stay under the 14 KB inline budget.

## 0.19.0 (2026-11-01)
Audit suggestions G-01 and G-02: `/mdcat-passing-marks/` (minimum marks table + live eligibility check, `[am_elig_check]`) and `/uhs-mbbs-admission/` (UHS 2026-27 official schedule from `data/calendar.json` `seasons.uhs`, `[am_season]`, next-step line in JS). New token `{session:<slug>}`. Details in `HANDOFF.md`.

## 0.18.1 (2026-11-01)
F-24 follow-up: comma message also where a browser gives no `badInput` (field typed into but empty), and no " marks" after labels with "score" or "%".

## 0.18.0 (2026-11-01)
Audit 2026-11-01, Phase 4 (UX): F-21 predictor saved-aggregate chips below the input with a "Using your saved…" line, F-22 header nav one line at 375 px, F-24 comma message and no "marks marks", F-18 in-content links to IBCC, percentage, report, open data and embed pages. F-20 verified (Lighthouse target-size passes), no change. Details in `HANDOFF.md`.

## 0.17.0 (2026-11-01)
Audit 2026-11-01, Phase 1: F-01 eligibility floors in calculators and predictor (UHS MDCAT 55/50 + HSSC 60, UET Taxila, Air, Bahria, COMSATS, ITU, UAF, NED). New `[am_eligibility]` shortcode, `check.py [elig]`. Details in `HANDOFF.md`.

## 0.16.0 (2026-11-01)
Audit 2026-11-01, Phase 3 (content and trust): F-02, F-03, F-04, F-05, F-09, F-10, F-11, F-12, F-13, F-14, F-15 fixed; F-26 already sourced. Details in `HANDOFF.md`. New `check.py` checks `[sources]` and `[copy]`.

## 0.15.1 and earlier
See `HANDOFF.md` sections per phase.
