# IAM-SSO-CORE Design System (Master Source of Truth)

> **Acuan**: Obsidian `Design System Hub/00-Design-System-Hub-Overview` (`ui_ux_architect` + `ui_styling_engineer`)  
> **Persona Visual**: VyrnForge Dark Tech & Cyber Security  
> **Palet Utama**: Electric Cyan & Trust Blue (`--color-cyan-500` / `--color-cyan-600` + Emerald Status `--color-emerald-400`)  
> **Mode Tema**: Seamless Dual Theme (Default Dark Mode `html.dark` + Light Mode `:root` via `<x-theme-toggle />`)

---

## 1. Arsitektur Token 3-Layer (`public/css/design-tokens.css`)

1. **Layer 1 — Primitive Tokens**:
   - Skala warna dasar: `--color-slate-50..950`, `--color-cyan-300..950`, `--color-blue-500..600`, `--color-emerald-200..700`, `--color-amber-200..700`, `--color-rose-200..950`.
   - Skala spasi (kelipatan 4/8px): `--space-1` (4px) hingga `--space-10` (40px).
   - Skala tipografi: `--font-sans` (`IBM Plex Sans`), `--font-mono` (`JetBrains Mono`), `--font-size-xs` hingga `--font-size-2xl`.
   - Radius & durasi transisi: `--radius-sm` (8px), `--radius-md` (12px), `--radius-lg` (16px), `--radius-full` (9999px), `--duration-fast` (150ms).

2. **Layer 2 — Semantic Tokens (`.dark` & `:root` Light)**:
   - Surface & Teks: `--color-bg-canvas`, `--color-bg-elevated`, `--color-bg-surface`, `--color-bg-surface-hover`, `--color-bg-subtle`, `--color-text-primary`, `--color-text-secondary`, `--color-text-muted`, `--color-text-highlight`.
   - Brand & Status: `--color-primary`, `--color-primary-hover`, `--color-on-primary`, `--color-link`, `--color-link-hover`, `--color-success`, `--color-warning`, `--color-danger`.
   - Border & Ring: `--color-border`, `--color-border-strong`, `--color-focus-ring`.

3. **Layer 3 — Component Tokens**:
   - `--card-bg`, `--card-border`, `--card-radius`, `--card-padding`, `--card-shadow`
   - `--button-bg`, `--button-fg`, `--button-hover-bg`, `--button-ghost-bg`, `--button-ghost-fg`, `--button-danger-bg`, `--button-danger-fg`
   - `--input-bg`, `--input-border`, `--input-fg`, `--input-focus-ring`
   - `--sidebar-bg`, `--sidebar-border`, `--nav-link-active-bg`, `--nav-link-active-fg`
   - `--code-bg`, `--code-border`, `--code-fg`
   - `--secret-banner-bg`, `--secret-banner-border`, `--secret-code-bg`, `--secret-code-fg`

---

## 2. SOP & Aturan Komponen (`ui_styling_engineer`)
- **No Arbitrary Colors**: Dilarang menulis `#hex` langsung di file `.blade.php`. Seluruh warna wajib menggunakan token CSS atau kelas semantik dari `public/css/design-tokens.css`.
- **Accessible Contrast First**: Teks utama dan sekunder memiliki rasio kontras $\ge 4.5:1$ baik di Dark Mode maupun Light Mode.
- **Blade Primitives (`resources/views/components/`)**:
  - `<x-badge :variant="..." />`
  - `<x-secret-banner :label="..." :value="..." />`
  - `<x-theme-toggle />`
