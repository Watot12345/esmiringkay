# Emiringkay: UI/UX Structure

## 1. Experience goals
Calm, playful, and low-pressure. A user should go from opening the app to meeting someone in **under 3 taps**.

## 2. Information architecture (sitemap)
```
Emiringkay
|-- Landing (public)
|-- Onboarding
|     |-- Sign in (Google / phone)
|     |-- Age confirmation + guidelines
|     |-- Pick display name, avatar, interests
|-- Home (tab bar)
|     |-- Meet        (Vibe select -> Matching -> Room)
|     |-- Plaza       (group rooms, events, daily prompt)
|     |-- Circle      (Keeps: friends, rivals, sparks, confidants + DMs)
|     |-- Me          (profile, badges, karma, settings)
|-- Room (full screen)
|-- Post-call screen (Keep / Report / Rate mood)
|-- Safety Center, Help, Terms
```

## 3. Key screens

### Home / Meet
- Big central **Start** button with soft pulsing glow.
- Vibe chips: Chill, Vent, Play, Spark, Debate.
- Mode toggle: Text | Video | Mask Video.
- Live counter: "1,284 people online".

### Matching screen
- Calming animation (floating orbs converging), tip of the moment, Cancel button.
- Relaxed filters shown honestly ("Widening search...").

### Room screen (video)
```
+----------------------------------+
| [Vibe: Chill]     00:42   [Skip] |
|                                  |
|        STRANGER VIDEO            |
|                                  |
|            +--------+            |
|            | YOU    |            |
|            +--------+            |
|----------------------------------|
| chat messages...                 |
| [ type a message ......... ] [>] |
|----------------------------------|
| Mic  Cam  Mask  Activities  Keep |
|                      [Report]    |
+----------------------------------+
```
- **Skip** top right, always one tap. **Report** and **Panic Skip** always reachable.
- Camera consent: both start with blur; each side taps "Show me" to unblur.
- **Activities drawer**: Doodle board, Trivia duel, Breathe together, Music, Icebreaker card.
- Reactions float across the screen.

### Post-call screen
- "How do you feel?" 5 emoji mood slider (feeds the stress-relief metric).
- Buttons: **Keep as...** (Friend, Rival, Spark, Confidant), Next, Report.
- Keep is revealed only if both choose it.

### Plaza
- Cards for live rooms with topic, participants, and a Join button.
- Daily prompt at top ("What made you smile today?").
- Events calendar and badges.

### Circle
- List of Keeps with presence dots, relationship tag, and last message.
- Rival leaderboard, call-back button, mute/leave controls per Keep.

### Me
- Avatar and mask collection, badges, karma, language, interests, privacy and safety settings, delete account.

## 4. Core flows
1. **First run**: Landing -> Sign in -> Age gate + guidelines -> Profile setup -> Home.
2. **Meet someone**: Choose Vibe/mode -> Start -> Matching -> Room -> Post-call.
3. **Keep**: Post-call -> pick type -> wait for mutual -> Connection appears in Circle.
4. **Report**: Report -> category -> optional note -> confirmation -> Room ends and next match.
5. **Join Plaza**: Plaza -> choose room -> permission check -> join.

## 5. Component inventory
Buttons (primary, soft, danger), Vibe chips, Presence dot, Avatar/Mask picker, Video tile, Chat bubble, Reaction burst, Activity card, Toast, Bottom sheet, Modal, Tabs, Progress orb, Badge, Empty states, Skeleton loaders.

## 6. Interaction and motion
- Ease-out transitions at 200-300 ms; no abrupt flashes.
- Haptics on mobile for match found and Keep.
- Respect `prefers-reduced-motion`: replace animations with fades.

## 7. Accessibility
- Contrast AA minimum, scalable text, focus rings.
- Full keyboard navigation and screen-reader labels for controls.
- Live captions option and visual alerts for audio cues.
- Color is never the only signal (icons plus labels).

## 8. Responsive behavior
- Mobile-first (360 px base), portrait video layout with chat overlay.
- Tablet/desktop: side-by-side video and chat, activities in a right panel.

## 9. Copy and tone
Warm, short, playful, never judgmental. Example: "Nobody here yet... stretch, we'll find someone." Errors are human: "Connection hiccup. Retrying."

## 10. Safety UX patterns
- Guidelines shown as a friendly 3-card intro, not a wall of legal text.
- Safety Center reachable from every screen.
- Clear indicators when camera or mic is live.
