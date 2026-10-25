# Manual bilingual Firebase content

Chinese localisation is manual only. Do not configure or deploy any automatic
translation provider for HC Trader content.

## Runtime rule

- Static Flutter UI text is resolved through `AppLocalizations`.
- Firestore content is read through `localizedValue`, `getLocalizedText`, or the
  model-specific localized helpers.
- Chinese mode reads `zh`, falls back to `en`, then returns an empty string or
  legacy fallback.
- No app screen, Cloud Function, or admin workflow calls a translation API.

## Firestore shape

Existing compatibility fields may remain as plain English strings, but every
user-facing admin record must include reviewed bilingual sidecars:

```json
{
  "title": "Risk Management",
  "title_i18n": {
    "en": "Risk Management",
    "zh": "风险管理"
  },
  "description": "Learn how to control risk before entering trades.",
  "description_i18n": {
    "en": "Learn how to control risk before entering trades.",
    "zh": "学习如何在进场前控制交易风险。"
  }
}
```

Knowledge Centre and Final Examination seed data use `locales.en` and
`locales.zh` blocks so complete lessons, options, answers, explanations, and
image metadata are stored together.

## Admin behavior

Admin and publisher forms must collect English and Simplified Chinese
separately for production user-facing fields. If one language is filled and the
other is missing, the form warns or rejects the save. Admin input is never sent
to a translation service.

## Migration and audit

Use manual backfill scripts and audit tools only:

```powershell
cd mobile/hc_trader/tools
npm run audit:zh
npm run backfill:final-exam-zh -- --project=hcgaming-31d5d
```

The final-exam backfill uses the checked-in static Chinese map and refuses to
write if a known manual Chinese value is missing. Review audit output before any
production write.

## Testing checklist

- Run `npm run check` in `functions`.
- Run `npm run audit:zh -- --static-only` in `tools`.
- Run `flutter analyze` in `mobile/hc_trader`.
- Run `flutter test` in `mobile/hc_trader`.
- Verify Chinese language switching displays Firestore `zh` values.
- Verify missing `zh` falls back to `en` without any network translation call.
