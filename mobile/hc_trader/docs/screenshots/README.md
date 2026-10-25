# Coursework screenshots

These PNGs are generated from the production Flutter widgets by
`tools/generate_coursework_screenshots_test.dart` at fixed phone and tablet
viewports.

- `coursework-overview.png` — candidate journey, assignment and 70/30 result
- `coursework-assignment-player.png` — five-question player and chart workflow
- `coursework-theme-management.png` — administration theme bank

Regenerate with:

```text
flutter test tools/generate_coursework_screenshots_test.dart --update-goldens
```
