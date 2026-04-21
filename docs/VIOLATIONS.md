# Vehicle Violations

> **Status:** ✅ built & live in production. Schema verified, controller/UI tested in dev. Auto-bill flow uses Cardknox `cc:sale` (verified command name) — actual charge has not yet been put through against a real Cardknox merchant on this codebase.

## Schema (verified by migration run)

`vehicle_violations` columns:
- Subject: `vehicle_id`, `reservation_id`, `customer_id` (auto-linked) · `plate`, `plate_state`
- Type & jurisdiction: `type` (parking, red_light_camera, speed_camera, bus_lane_camera, school_bus_camera, toll_evasion, registration, inspection, moving_violation, other) · `jurisdiction` (NY/NJ/CT/PA/MA/MD/VA/DC/OTHER) · `issuing_agency` (free text — NYC DOF, NYC DOT, NYPD, NYS DMV, school district, etc.)
- IDs: `summons_number`, `citation_number`, `issue_number`
- When/where: `issued_at`, `due_date`, `location`, `borough_or_county`
- Money: `fine_amount`, `late_fee`, `admin_fee` ($25 default) — `paid_amount` — `total_due` (auto-recalc)
- Status: `new` → `received` → `renter_notified` → `renter_billed` → `paid_by_renter` (or `paid_by_us`/`disputed`/`dismissed`)
- Evidence: `photo_path`, `document_path` (PDF), `evidence` (JSON of additional photos)

## Auto-link logic (verified in code)

When a violation is logged, `VehicleViolation::autoLink()`:
1. Match `Vehicle.license_plate` (uppercased, spaces stripped) against the violation's `plate`
2. Find the `Reservation` for that vehicle where `pickup_date <= issued_at <= actual_return_date` (or open-ended if not yet returned)
3. Set `vehicle_id`, `reservation_id`, `customer_id` accordingly

If no rental matched, the violation is still saved with the plate; the UI shows "⚠ No rental matched" and prompts the operator to assign manually.

## Bill-renter action

Verified flow:
1. Operator clicks **💳 Bill Renter** on the violation Show page
2. If a `ReservationHold` exists for that reservation: charge that card via `SolaPaymentsService::charge(account: 'high_rental', ...)` → Cardknox `cc:sale`
3. If no hold: just bump `reservations.outstanding_balance` by `total_due` (will be collected on return)
4. Either way: create a `RentalPayment` (type `violation`), update violation status to `paid_by_renter`, log to `communication_logs`

## Routes (verified)

```
GET    /violations                  Index (list + filters)
GET    /violations/create           New violation form
POST   /violations                  Save new violation (auto-links)
GET    /violations/{id}             Show
PUT    /violations/{id}             Update amounts/status
POST   /violations/{id}/bill-renter Trigger Cardknox charge + log
```

## What's not yet built

- Bulk import of violation notices (would require a CSV export from the agency, which isn't readily available — most agencies mail paper)
- Auto-detection from scanned mail (could use Anthropic vision on photos of mailed tickets to auto-extract summons #, agency, amount, plate)
- Dispute workflow (currently just a status — no track of submitted dispute, hearing date, outcome)

---
Files:
- `database/migrations/2026_04_21_210000_create_vehicle_violations_table.php`
- `app/Models/VehicleViolation.php`
- `app/Http/Controllers/VehicleViolationController.php`
- `resources/js/Pages/Violations/{Index,Create,Show}.vue`
