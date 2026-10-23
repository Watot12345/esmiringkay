# Emiringkay: Theme and Design System

## 1. Concept: "Soft Night Plaza"
A cozy nighttime town square lit by lanterns: warm, friendly, slightly dreamy. Calming indigo base with playful, glowing accents.

## 2. Color tokens
| Token | Dark (default) | Light | Use |
|---|---|---|---|
| `--bg` | #0F1020 | #FAF8FF | App background |
| `--surface` | #1A1B33 | #FFFFFF | Cards, sheets |
| `--surface-2` | #24264A | #F0ECFA | Raised elements |
| `--text` | #F4F2FF | #1B1B2F | Primary text |
| `--text-muted` | #A9A6CC | #6B6890 | Secondary text |
| `--primary` | #8B7CFF | #6C5CE7 | Main actions |
| `--accent-coral` | #FF8A7A | #F2685A | Spark, highlights |
| `--accent-mint` | #5EEAD4 | #14B8A6 | Success, Chill |
| `--accent-gold` | #FFD36E | #E8A317 | Badges, Rival |
| `--danger` | #FF5C7A | #E11D48 | Report, destructive |
| `--border` | #34376A | #E2DCF3 | Dividers |

### Vibe colors
- Chill: mint, Vent: soft blue (#7FB3FF), Play: gold, Spark: coral, Debate: violet.

### Relationship colors
- Friend: mint, Rival: gold, Spark: coral, Confidant: soft blue.

Contrast: all text/background pairs must meet WCAG AA (4.5:1).

## 3. Typography
- Headings: **Poppins** or **Sora** (rounded, friendly), weight 600-700.
- Body: **Inter**, weight 400-500.
- Scale (rem): 0.75, 0.875, 1, 1.125, 1.25, 1.5, 2, 2.5.
- Line height 1.5 for body, 1.2 for headings.

## 4. Shape, spacing, elevation
- Radius: 12 px (inputs), 20 px (cards), 999 px (chips, buttons).
- Spacing scale: 4, 8, 12, 16, 24, 32, 48.
- Shadows are soft glows, not hard drops: `0 8px 30px rgba(139,124,255,0.25)`.
- Glassmorphism on overlays: blur 16 px with 60% surface opacity.

## 5. Motion
- Duration: 150 ms (micro), 250 ms (standard), 500 ms (hero).
- Easing: `cubic-bezier(0.22, 1, 0.36, 1)`.
- Signature: floating "orbs" for matching, soft ripple on Keep, gentle confetti for badges.
- Reduced-motion fallback: fades only.

## 6. Iconography and illustration
- Rounded line icons (Lucide), 1.75 px stroke.
- Illustrations: flat shapes with grainy gradients, friendly faceless characters.
- Masks: a collection of playful, original avatar masks (animals, spirits, abstract shapes). Do not copy existing characters or brands.

## 7. Tailwind config snippet
```js
// tailwind.config.js
export default {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        bg: 'var(--bg)',
        surface: 'var(--surface)',
        primary: 'var(--primary)',
        coral: 'var(--accent-coral)',
        mint: 'var(--accent-mint)',
        gold: 'var(--accent-gold)',
        danger: 'var(--danger)',
      },
      borderRadius: { card: '20px', pill: '999px' },
      fontFamily: { display: ['Sora', 'sans-serif'], body: ['Inter', 'sans-serif'] },
      boxShadow: { glow: '0 8px 30px rgba(139,124,255,0.25)' },
    },
  },
}
```

## 8. Brand voice
Friendly, warm, a little cheeky. Short sentences. Taglines to test:
- "Say hi to someone new."
- "Meet. Breathe. Connect."
- "Strangers today, friends tomorrow."

## 9. Logo direction
Wordmark "Emiringkay" in a rounded display face with a small glowing orb replacing a letter dot. Provide a monochrome version and an app icon (orb on indigo). Make sure the final logo is original and does not resemble existing brands.

## 10. Dark/light rules
Dark is default (stress relief at night). Light mode is fully supported via the same tokens. Respect system preference with a manual toggle.
