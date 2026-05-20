# Sakai Gradebook Read-Only Test (Tsugi)

Tsugi LTI tool that exercises Sakai's **Allow External Tool read-only access to entire gradebook** (`allowgradebookreadonly`) via LTI Advantage AGS and NRPS.

## Sakai setup

1. External Tools admin: enable **Allow External Tool to create grade columns** and **Allow External Tool read-only access to entire gradebook** for this tool.
2. Register the tool launch URL as `index.php` (Tsugi default).

## What it does on launch (`index.php`)

- Lists all line items (including other tools' columns when read-only view is on)
- GETs each line item's detail
- GETs results for every line item
- Loads NRPS roster for student names
- Renders a gradebook-style table plus debug tabs

## Pages

| File | Purpose |
|------|---------|
| `index.php` | **LTI launch entry** — gradebook + API debug tabs |
| `launch.php` | Raw LTI post / session dump (optional) |
