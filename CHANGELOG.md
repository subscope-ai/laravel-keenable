# Changelog

All notable changes to `subscope-ai/laravel-keenable` are documented here.

## 1.0.0 - 2026-10-20

First stable release.

### Added

- `KeenableClient` and the `Keenable` facade for the `POST /v1/search` and `GET /v1/fetch` endpoints.
- `Keenable::search()` with `pro` and `realtime` modes, result count, date/time filters, and configurable snippet length.
- `Keenable::fetch()` with live fetching of non-indexed URLs, character limits, and optional LLM extraction via `prompt`.
- `KeenableSearchTool` and `KeenableFetchTool` implementing `Laravel\Ai\Contracts\Tool`, registered through the service provider.
- `SearchResponse`, `FetchResponse`, and `SearchResult` DTOs, plus the `SearchMode` enum.
- Typed exceptions for authentication, credits, forbidden keys, rate limits, validation errors, and server errors, with status code, response body, and rate-limit metadata.
- Configurable billing: API key, keyless public endpoints, or pay-per-request `x402` paths.
- Publishable config (`--tag=keenable-config`) and environment-driven setup.
- Test suite built on Pest and Orchestra Testbench.
