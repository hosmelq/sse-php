# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.0.0 - 2026-11-16

- Require PHP 8.4 or newer and support Guzzle 8.
- Parse streams as the WHATWG standard describes, including CR line endings and a byte order mark, and yield only events with data.
- Throw `SSEProtocolException` for responses that are not event streams, and `SSEConnectionException` when reading fails.
- Keep the Guzzle client's timeouts, and add `getRetry()` and resuming from the `Last-Event-ID` header.
- Move the documentation to `docs/`.

See [UPGRADE.md](UPGRADE.md) for upgrade instructions.

## v0.1.0 - 2025-07-13

- Initial release.
