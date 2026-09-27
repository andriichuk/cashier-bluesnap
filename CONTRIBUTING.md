# Contributing

Pull requests are welcome. Keep changes focused and include tests for observable behavior.

```bash
composer install
composer check
```

The test suite must not call live BlueSnap services or require credentials. Use the recording HTTP client for request/response fixtures and avoid including real payment or customer data.
