# Local Capester connection check

```powershell
php artisan assistant:capester-ping
```

This CLI-only check starts a separate Codex run using the current machine user's existing Codex sign-in. It does not connect to this desktop chat, resume a conversation, read chat history, send application data, or create a remote-to-local bridge. Codex must already be installed on PATH and authenticated. No credentials or configuration are changed. Running it contacts the configured Codex service and may consume account usage.

The command refuses environments other than `local` and `testing`. Child processes retain only runtime, home/auth-location and locale environment variables; application secrets and other inherited environment variables are explicitly removed. The invocation also sets `features.shell_tool=false`. Global Codex guidance or skills may still be discoverable through the existing user home, so this check is not a guarantee of full filesystem or network isolation.

The command sends one fixed prompt: `Reply exactly CAPESTER_PONG. Do not use tools, read files, or execute commands.` There is no arbitrary prompt option or web endpoint. The process receives an argument array rather than a constructed shell command, uses a new empty temporary working directory, ignores user configuration, requests a read-only sandbox and an ephemeral run, and times out after 60 seconds. Existing authentication is still resolved through Codex's normal local authentication mechanism.

Success requires an exact `CAPESTER_PONG` message and a completed JSONL turn. Tool/command/file-change events, provider errors, malformed output, wrong replies and incomplete turns fail. Provider diagnostics are discarded rather than printed. Output is bounded to 64 KiB. The read-only sandbox and fixed no-tools prompt restrict the requested operation; rejecting a tool event is detection, not a claim that the parser can prevent an already emitted tool call. Unexpected files are never recursively deleted.

Verify installation/sign-in separately if the generic failure appears. Do not paste credentials or raw provider diagnostics into application logs or chat. A failed connection does not grant permission to disable sandboxing or change security configuration.

Unit tests use a mocked process and make no network calls:

```powershell
php vendor/bin/phpunit --no-configuration tests/Unit/Assistant/CapesterConnectionTest.php
```

The future workflow remains separate: remote assistant sends explicitly scoped context to Capester; a local fix receives tests and review; an approved deployment aligns reviewed Git versions. This handshake implements none of those repair, data-access or deployment capabilities.
