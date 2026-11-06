# AI Provider Setup

## Required backend variables

- `OPENAI_API_KEY`
- `WHISPER_MODEL`
- `AI_MIN_CONFIDENCE`

Additional provider variables should be added only when their implementations are connected. Never send provider secrets to Web or Expo.

## Required behavior

Configure provider availability before enqueueing audio analysis. Missing credentials must produce an unavailable status, not fabricated results. Low-confidence output remains provisional and requires mentor confirmation. Timeout and retry behavior should remain observable through job and audit records.

## Provider status

Provider interfaces are implemented and unconfigured paths do not fabricate Tajweed analysis. Live speech, alignment, and Tajweed quality require external credentials and validated test recordings.
