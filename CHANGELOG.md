# Changelog

## 2.1.0

- Support preload and partial detection with `isPreload()`, `isPartial()`, `is_fire_preload()` and `is_fire_partial()`. Advanced flags require an enabled `X-FireLine` header.
- Share asset versions across service/facade resolutions within a request, without retaining them for later worker requests. Include versions on middleware-prepared normalized, validation and early responses.
- Bind the actual middleware request for consistent service/helper resolution in Spark's HTTP dispatcher and tests.
- Vary responses on all three FireLine representation headers while preserving existing cache policy and `Vary: *`.
- Preserve explicit empty and `"0"` page titles; reject invalid asset-version header values.
- Correct the partial documentation to return JSON render envelopes and document safe preload and deployment-version usage.
- Extend the native `Spark\Testing` suite with asset-version propagation/isolation, advanced headers and title edge cases. Add a real browser partial fixture used by the JS integration suite.

Composer resolves this release by its Git tag; no fixed `version` field is needed in composer.json.
