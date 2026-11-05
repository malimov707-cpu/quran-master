# Mobile Setup

The Expo application is in `mobile/`.

```sh
cd mobile
npm ci
npx expo start
```

Student, mentor and parent routes use the shared `/api/v1` API. Native audio and real-device behavior require Expo development builds and physical-device testing. Browser mode is useful for layout but is not an audio acceptance test.
