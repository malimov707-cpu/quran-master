# Quran Audio Acceptance

## Automated status

- Verified millisecond alignment import validation passes.
- Missing alignment is returned as `synchronization: none`.
- Duplicate and overlapping timestamps are rejected by import tests.
- Full Surah audio and mobile synchronization code pass automated bundle/type checks.
- Word-level timestamps are never synthesized from ayah ranges.

## External acceptance required

- Licensed audio source and per-reciter usage rights.
- Verified alignment data for each released reciter/surah.
- Audio duration validation against each release edition.
- Physical device playback, buffering, interruption, and background audio tests.
- Confirmation of canonical 6236-ayah metadata and translation source provenance.
