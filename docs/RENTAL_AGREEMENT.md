# Rental Agreement PDF

> **Status:** ✅ **template verified.** Matches the user-supplied reference PDF (`rentalagreement5736-20260421195733.pdf`) for structure and legal clauses, plus adds the user-supplied custom clauses (CC insurance 24-hour rule, $50 smoking, $10 EZ Pass, $20 after-hours, Monsey/Monroe hours, Beis Din venue).

## ✅ Verified content (matches reference PDF)

### Header
- HighRENTAL brand
- Office email, phone, street address (from reference)
- RA# status box (upper right)
- QR code position preserved

### Grid sections
- Renter Information (name, DL#, email, address, DOB, DL exp, phone, ZIP, CC brand/last4/exp)
- Rental Information (date out, date due, pickup/return location, time out, time in, insurance source)
- Vehicle Information (plate, brand, class, model, year, VIN, color, odo out, fuel out) + stylized car-top SVG
- Charge Information (rate × days, discount/weekend credit, sales tax, add-ons, total, paid, outstanding, security deposit)

### Terms & Conditions (11 sections, matches reference + user additions)

1. **Rental; Indemnity; Personal Property; Warranties** — authorized-driver rules, indemnity, no warranties
2. **Condition and Return of Vehicle** — bailment, final inspection, after-hours return
3. **Responsibility for Damage or Loss** — regardless of fault; includes:
   - **CC insurance policy (user-specified):** customer pays first; CC reimburses customer; if CC declines, customer takes responsibility; **24-hour rule** — unpaid after 24 hours → card on file is charged
   - **Exclusive repair rights** — no third-party mechanic
   - **Insurance proceeds** — belong to High Car Rental
4. **Prohibited Uses & Fees** — includes the user-specified fee schedule:
   - **$50** smoking fee
   - **$10** EZ Pass per use (auto-charged, no cash accepted)
   - Mileage overage fee for long distances
   - **$20** after-hours service fee
   - Customer consents to all additional fees on rental agreement being charged to card on file
   - **"All CC transactions are charged under the name 'High Rental' (or a related entity — for example 'Auto Go')."**
   - **Business hours:** Monsey Sun-Thu 9-6, Fri 9-12:30 · Monroe Mon-Thu 9-6, Fri 9-12:30
   - **Weekend credit:** rentals covering Fri+Sat+Sun auto-credit $20
5. **Insurance** — waiver clause
6. **Responsibility to Others; Accidents/Incidents** — including Mexico prohibition, incident reporting
7. **Payment; Charges** — reserve, final audit
8. **Tolls, Traffic Violations, Other Charges** — customer responsible; we release info to authorities; card on file auto-charged
9. **Personal Information; Communications** — consent for tel/SMS/email (including auto-dialer)
10. **Beis Din Dispute Resolution** — Beis Din Maysharim of Monsey NY as exclusive venue; secular court waived; prevailing party gets costs and reasonable attorney's / to'en's fees
11. **Miscellaneous** — no oral modification, governed by rental-commencement jurisdiction (subject to Beis Din), severability, **jury-trial waiver**

### Signature block (footer)
- Captured signature image (when present)
- Renter name
- Date
- RA#
- Event (when generated as part of a revision, e.g. `pickup` / `rented` / `return`)
- Signature metadata line: timestamp, IP, SHA256 fingerprint (from `signatures` table) — this is evidence-grade provenance

## ✅ Verified behavior

- Generated via DomPDF (`barryvdh/laravel-dompdf`)
- Preview route: `GET /rental/reservations/{id}/agreement/preview` — opens in browser tab without saving
- Generate route: `POST /rental/reservations/{id}/agreement` — saves to `public/reservations/{id}/rental-agreement-...pdf` and sets `reservation.lease_agreement_path`
- Also auto-generated on every Reservation state change via `ReservationObserver` → each revision stored in `agreement_revisions` with SHA256 hash chain

## 🟡 Known cosmetic differences from HQ reference

- Layout width: letter portrait (same as reference)
- Font: DejaVu Sans (DomPDF default) — reference uses a different but similar sans
- The stylized car top in the Vehicle Information box is a simple inline SVG; the reference uses a slightly different drawing
- QR code **not yet rendered** — we have the placeholder space but haven't wired a QR image generator

## Files

- `resources/views/rental/agreement.blade.php` — main template
- `resources/views/rental/return_receipt.blade.php` — return receipt template (separate doc type)
- `app/Http/Controllers/RentalAgreementController.php` — generate/preview routes
- `app/Services/AgreementRevisionService.php` — creates hash-chained PDF snapshots
