# Blockrium Frontend Polish Design

## Goal

Make the mobile app feel faster, clearer, and more native while preserving the
existing dark teal Blockrium visual identity and the current backend contracts.
The work should reduce perceived waiting, improve touch confidence, and make
the most-used flows easier to understand without rewriting navigation or
changing API ownership.

## Scope

### Shared app shell

- Keep the current React Navigation structure and lazy-loaded tabs.
- Improve shared screen, header, card, button, input, loading, and error
  primitives so fixes apply consistently across screens.
- Preserve cached content during refreshes and make offline states actionable.
- Ensure interactive controls expose accessible labels and comfortable touch
  targets.

### Home

- Keep the KYC waiting-list card and backend-controlled visibility.
- Prioritize the greeting, wallet/mining status, KYC state, daily actions, and
  rewards before secondary discovery content.
- Avoid blocking the entire screen on lower-priority requests.
- Keep the page scrollable, but move long repeated content to virtualized lists
  where the existing flow supports it.

### High-use flows

- Mining: make the primary mining action and live state unmistakable.
- Wallet: clarify balances, send/receive actions, and transaction loading/error
  states.
- Tasks and rewards: improve list rendering, completion feedback, and empty
  states.
- Profile and settings: standardize account status, controls, and destructive
  action presentation.
- Bottom navigation: improve active state, pressed state, labels, and safe-area
  spacing without changing route names.

## Data and performance rules

- Reuse the existing React Query cache and endpoint contracts.
- Do not add polling or new network requests unless an existing screen already
  requires live state.
- Prefer stale cached data plus a visible refresh indicator over a blank screen.
- Use FlashList or FlatList for genuinely long collections; do not virtualize
  small static sections solely for style.
- Defer secondary content only after the primary screen content is usable.

## Visual direction

- Keep the existing near-black, teal, blue, and warm-gold palette.
- Use the existing Jaro display face sparingly for primary metrics and Roboto
  Condensed for readable supporting copy.
- Use one signature interaction language: short press feedback plus restrained
  entrance motion. Avoid adding decorative animation to every card.
- Preserve strong contrast and minimum 44px touch targets where practical.

## Error and empty states

- Every network-backed section should have a useful loading, empty, and retry
  state.
- Error copy should explain what the user can do next.
- A failed secondary request must not hide already-loaded primary content.
- Duplicate submissions should be disabled while a request is in flight.

## Verification

- Run TypeScript, ESLint, Prettier, and existing Jest tests after each pass.
- Run an Android debug/release build when the shared shell and navigation edits
  are complete.
- Manually check Home, Mining, Wallet, Tasks, Profile, offline mode, and the KYC
  waiting-list flow on a small and large Android viewport.
- Check that backend-disabled KYC hides both the card and popup.

## Out of scope

- Replacing React Navigation.
- Rewriting the API client or changing backend endpoints.
- Adding new monetization, KYC providers, or token rules.
- A full redesign of every existing screen in one pass.
