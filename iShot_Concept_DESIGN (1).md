# Design System Inspired by iShot Concept Photography Studio

## 1. Visual Theme & Atmosphere

The iShot Concept design system embodies premium African creativity, cinematic storytelling, and precise professional photography. The aesthetic should feel like a high-end visual studio based in Monrovia with global ambition: bold, confident, emotionally rich, and visually refined. Deep brand navy creates a gallery-like foundation where images can breathe, while orange, blue, teal-blue, and green accents reflect the logo’s lens-aperture identity and the brand’s energy, innovation, growth, and visual clarity.

The design must communicate that iShot Concept does more than take pictures: it captures emotion, identity, legacy, and culturally rich stories. The interface should feel intentional and memorable, using strong contrast, clean typography, generous whitespace, full-bleed photography, and focused calls-to-action such as “Book Your Session” and “Let’s Create That Magic.” Every page should support the brand promise: **Your moments deserve more than just pictures — they deserve to be remembered, felt, and celebrated.**

**Key Characteristics**
- Premium, gallery-inspired photography aesthetic rooted in iShot Concept’s deep navy identity
- African creative excellence expressed through bold imagery, emotion, culture, and legacy
- Minimalist, photography-forward layout philosophy that allows portraits, weddings, corporate shoots, reels, and concepts to lead visually
- Lens-aperture visual language inspired by the iShot Concept logo structure
- Strategic use of orange, blue, teal-blue, and green brand accents for calls-to-action, highlights, and visual rhythm
- Professional, contemporary aesthetic for birthdays, portraits, weddings, engagements, corporate shoots, studio sessions, outdoor sessions, creative direction, videography, and short films
- Premium client-experience tone: creative, precise, stylish, intentional, and unforgettable
- Strong emphasis on visual breathing room, cinematic framing, and content prominence

## 2. Color Palette & Roles

### Primary
- **iShot Navy** (`#172D60`): Primary UI background, dominant brand color, header/footer foundation, hero overlays, dark gallery sections, and premium content areas. This color should carry the strongest visual association with iShot Concept.
- **Deep Studio Navy** (`#101F44`): Secondary dark tone derived from the brand navy for depth variation, overlay gradients, dark cards, hover states, and section layering.

### Accent Colors
- **Signature Orange** (`#F26A22`): Primary action accent for booking prompts, featured calls-to-action, important highlights, and high-energy moments. Use for “Book Your Session,” “Start a Concept,” and “Contact Us” actions.
- **Creative Green** (`#47A743`): Supporting accent representing growth, fresh ideas, success, and positive confirmation states. Use sparingly in service highlights, confirmation messages, and visual storytelling accents.
- **Teal Blue** (`#329AD3`): Tertiary accent for calm creative balance, portfolio filters, secondary highlights, icon details, and soft visual transitions.
- **Lens Blue** (`#0071BF`): Strong secondary accent for links, secondary buttons, active states, and portfolio/category indicators.

### Interactive
- **Orange Button** (`#F26A22`): Primary call-to-action buttons, hover states, booking prompts, and lead-generation elements. High energy and highly visible against navy or white.
- **Blue Secondary Button** (`#0071BF`): Secondary call-to-action buttons, portfolio links, service detail buttons, and supporting interactive elements.
- **Teal Interactive** (`#329AD3`): Tertiary interactive elements, filter pills, icon hover states, subtle link emphasis, and supporting UI indicators.
- **Gray Secondary** (`#7A8595`): Low-emphasis secondary button states, disabled controls, muted interface elements, and utility actions.

### Neutral Scale
- **Pure White** (`#FFFFFF`): Primary text on dark backgrounds, card backgrounds, clean content surfaces, and photography gallery spacing.
- **Soft Off-White** (`#F7F9FC`): Light section background, form background, portfolio detail areas, and subtle content separation without harsh contrast.
- **Light Gray** (`#D8DEE8`): Secondary text on dark backgrounds, borders, dividers, metadata, and supporting copy.
- **Medium Gray** (`#545454`): Body text on light backgrounds, service descriptions, paragraph copy, metadata, and secondary headings.
- **Pure Black** (`#000000`): Maximum-contrast text or monochrome logo applications only; use sparingly.

### Surface & Borders
- **Border Gray** (`#D8DEE8`): Standard border color for cards, forms, inputs, image frames, and container divisions.
- **Subtle Divider** (`#B8C2D1`): Light separators, thin lines, portfolio category dividers, and low-emphasis layout rules.
- **Dark Divider** (`#223B78`): Border or divider on navy surfaces where subtle structure is required.

### Semantic / Status
- **Success Green** (`#47A743`): Success confirmations, booking submission states, client satisfaction indicators, and completed process states.
- **Warning Orange** (`#F26A22`): Attention states, required actions, urgent booking prompts, and high-priority notices.
- **Information Blue** (`#0071BF`): Informational callouts, contact prompts, service notes, and active navigation states.

## 3. Typography Rules

### Font Family
**Primary:** `system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`  
**Recommended Brand Alternative:** `"Montserrat", "Poppins", system-ui, sans-serif` for a clean premium photography feel when web fonts are available.  
**Logo / Script Accent:** `"Botaniy Regular", "Brush Script MT", cursive` for the “Concept” script styling only.  
**Secondary:** `Arial, sans-serif` for input and form elements.  
**Fallback stack:** `system-ui` → `Arial` → `sans-serif`

**Logo Note:** The `iSHOT` portion of the logo is a custom shape-based mark and should be used as the official image/SVG asset instead of being recreated with a font. The “Concept” wordmark should preserve the elegant handwritten identity shown in the brand brief.

### Hierarchy

| Role | Font | Size | Weight | Line Height | Letter Spacing | Notes |
|------|------|------|--------|------------|-----------------|-------|
| Display / Hero | system-ui | 48px | 700 | 56px | 0px | Large hero statements, emotional brand hooks, and primary page titles |
| Heading 1 | system-ui | 36px | 700 | 44px | 0px | Section headers such as About Us, Our Services, Portfolio, and Why Choose Us |
| Heading 2 | system-ui | 28px | 700 | 36px | 0px | Subsection headers, service group titles, and featured portfolio categories |
| Heading 3 | system-ui | 24px | 700 | 32px | 0px | Component headers, card titles, package names, and booking steps |
| Body Large | system-ui | 18px | 400 | 28px | 0px | Brand statements, featured copy, and premium intro text |
| Body | system-ui | 16px | 400 | 24px | 0px | Standard body copy, service descriptions, labels, and contact information |
| Body Small | system-ui | 14px | 400 | 28px | 0px | Secondary content, service notes, captions, footer links, and small descriptions |
| Caption | system-ui | 13px | 400 | 20px | 0px | Metadata, image captions, timestamps, package notes, and footnotes |
| Button / Link | system-ui | 14px | 600 | 20px | 0px | Interactive text, primary actions, booking links, and navigation links |
| Input | Arial | 13px | 400 | normal | 0px | Form field text and placeholders |

### Principles
- **Contrast-driven:** Dark navy backgrounds require white typography; light backgrounds use iShot Navy for headings and medium gray for readable body text.
- **Hierarchy through weight:** Bold (700) for all headings; semi-bold (600) for buttons and high-priority links; regular (400) for body and supporting copy.
- **Script used sparingly:** Use the Botaniy-style script only for branded “Concept” accents, signature moments, hero overlays, or premium callouts. Do not use script for body text, navigation, or service details.
- **Generous leading:** Line heights exceed font size by 1.5× to 1.75× for comfortable reading in gallery-like spaces.
- **System-first approach:** Use system fonts for speed and reliability; add Montserrat or Poppins only if the site build supports web fonts cleanly.
- **No decorative sizing:** Typography serves functional hierarchy; avoid arbitrary sizes outside the defined scale.
- **Emotion with discipline:** Hero copy may feel poetic, but the typography remains clean, readable, and premium.

## 4. Component Stylings

### Buttons

**Primary Button (Signature Orange)**
- **Background:** `#F26A22`
- **Text Color:** `#FFFFFF`
- **Font:** `system-ui`, `14px`, weight `600`, line-height `20px`
- **Padding:** `10px 20px`
- **Border Radius:** `3px`
- **Border:** `0px none`
- **Box Shadow:** `none`
- **Height:** `40px`
- **Width:** `auto`
- **Default Text Examples:** “Book Your Session”, “Let’s Create That Magic”, “Start a Concept”
- **Hover State:** Darken to `#D95718`, cursor `pointer`
- **Active State:** Increase shadow depth, slight scale reduction `0.98`

**Secondary Button (Lens Blue)**
- **Background:** `#0071BF`
- **Text Color:** `#FFFFFF`
- **Font:** `system-ui`, `14px`, weight `600`, line-height `20px`
- **Padding:** `10px 20px`
- **Border Radius:** `3px`
- **Border:** `0px none`
- **Box Shadow:** `none`
- **Height:** `40px`
- **Width:** `auto`
- **Default Text Examples:** “View Portfolio”, “Explore Services”, “See Our Work”
- **Hover State:** Darken to `#005F9F`, cursor `pointer`
- **Active State:** Darken to `#004F86`

**Ghost / Text Button (White Background)**
- **Background:** `#FFFFFF`
- **Text Color:** `#172D60`
- **Font:** `system-ui`, `14px`, weight `600`, line-height `18px`
- **Padding:** `11px 20px`
- **Border Radius:** `3px`
- **Border:** `0px none`
- **Box Shadow:** `none`
- **Height:** `40px`
- **Width:** `207px` (approximate)
- **Default Text Examples:** “Contact iShot Concept”, “View Packages”, “Send Inquiry”
- **Hover State:** Background to `#F7F9FC`, slight lift with `box-shadow: 0 2px 8px rgba(0,0,0,0.1)`
- **Active State:** Background to `#E8EEF7`

**Icon Button (Minimal)**
- **Background:** `rgba(0, 0, 0, 0)`
- **Text Color:** `#172D60`
- **Font:** `system-ui`, `14px`, weight `400`, line-height `18px`
- **Padding:** `0px`
- **Border Radius:** `0px`
- **Border:** `0px none`
- **Box Shadow:** `none`
- **Height:** `18px`
- **Width:** `18px`
- **Icon Style:** Use clean camera, aperture, arrow, play, gallery, Instagram, email, and phone icons
- **Hover State:** Color to `#F26A22`, opacity `0.85`, cursor `pointer`

### Cards & Containers

**Feature Card**
- **Background:** `#FFFFFF` or `#F7F9FC` for highlighted variants
- **Border:** `1px solid #D8DEE8`
- **Border Radius:** `3px`
- **Padding:** `32px`
- **Box Shadow:** `0 2px 8px rgba(0, 0, 0, 0.08)`
- **Text Color:** `#172D60` (headings), `#545454` (body)
- **Use Cases:** Service cards, photography packages, booking steps, why-choose-us points, client promise cards
- **Recommended Service Card Titles:** Birthday & Portrait Photography; Weddings, Renewals & Engagements; Corporate & Executive Shoots; Creative Direction & Concepts; Videography & Short Reels/Films; Studio & Outdoor Sessions
- **Hover State:** Box shadow lift to `0 4px 16px rgba(0, 0, 0, 0.12)`, slight scale `1.02`, optional top border or icon accent in `#F26A22`

**Dark Container (Primary Background)**
- **Background:** `#172D60`
- **Text Color:** `#FFFFFF` (primary), `#D8DEE8` (secondary)
- **Border Radius:** `0px` (full-width sections) or `3px` (contained cards)
- **Padding:** `72px` (section padding), `32px` (content padding)
- **Border:** `0px none` (default), `1px solid #223B78` (optional subtle divider)
- **Use Cases:** Hero sections, footer, mission/vision sections, featured portfolio strips, strong CTA sections
- **Preferred Copy:** “A creative photography brand turning moments into timeless visual experiences.”

**Light Container (Gallery / Content)**
- **Background:** `#FFFFFF`
- **Text Color:** `#172D60` (primary), `#7A8595` (secondary)
- **Border Radius:** `0px` (full-width) or `3px` (component)
- **Padding:** `72px` (section), `32px` (content)
- **Border:** `1px solid #D8DEE8` (optional)
- **Use Cases:** About Us, services listing, portfolio categories, contact sections, booking inquiry forms

**Image / Portfolio Card**
- **Background:** `#FFFFFF`
- **Border:** `0px none` or `1px solid #D8DEE8`
- **Border Radius:** `6px`
- **Padding:** `0px`
- **Overflow:** `hidden`
- **Image Treatment:** Full-bleed image, cinematic crop, strong subject focus, balanced negative space
- **Overlay:** `linear-gradient(rgba(23,45,96,0.05), rgba(23,45,96,0.55))` for text readability
- **Hover State:** Slight image scale `1.04`, overlay deepens, title reveals or moves upward subtly
- **Portfolio Categories:** Portraits, Weddings, Corporate, Creative Concepts, Studio, Outdoor, Reels/Films

### Inputs & Forms

**Text Input**
- **Background:** `#FFFFFF`
- **Text Color:** `#172D60`
- **Font:** `Arial`, `13px`, weight `400`, line-height `normal`
- **Padding:** `12px 16px`
- **Border:** `1px solid #D8DEE8`
- **Border Radius:** `3px`
- **Box Shadow:** `none`
- **Height:** `40px`
- **Focus State:** Border color to `#F26A22`, box shadow `0 0 0 3px rgba(242, 106, 34, 0.12)`
- **Placeholder Color:** `#B8C2D1`
- **Disabled State:** Background `#F7F9FC`, text color `#9AA6B6`, border color `#D8DEE8`

**Label**
- **Font:** `system-ui`, `16px`, weight `400`, line-height `24px`
- **Text Color:** `#172D60`
- **Margin Bottom:** `8px`
- **Display:** `block`

**Form Group**
- **Margin Bottom:** `20px`
- **Last child:** Margin bottom `0px`
- **Recommended Fields:** Full Name, Phone Number, Email Address, Service Type, Preferred Date, Shoot Location, Message / Concept Details
- **Submit Button Text:** “Book Your Session” or “Send Booking Inquiry”

### Navigation

**Header Navigation**
- **Background:** `#172D60`
- **Height:** Approximately `56px` to `72px` depending on logo size
- **Logo:** Official iShot Concept logo, preferably SVG/transparent PNG, left-aligned
- **Text Color:** `#FFFFFF` (nav items)
- **Font:** `system-ui`, `14px`, weight `600`, line-height `20px`
- **Padding:** `20px 32px` (horizontal/vertical)
- **Recommended Nav Items:** Home, About, Services, Portfolio, Creative Direction, Contact
- **Primary Nav CTA:** “Book Your Session” in `#F26A22`
- **Link Hover State:** Color to `#F26A22`, text decoration `none`
- **Active Link:** Color to `#F26A22`, optional underline `2px solid #F26A22`

**Footer Navigation**
- **Background:** `#172D60`
- **Text Color:** `#D8DEE8` (primary text), `#B8C2D1` (secondary)
- **Font:** `system-ui`, `14px`, weight `400`, line-height `28px`
- **Padding:** `32px` vertical, `32px` horizontal
- **Link Color:** `#329AD3` (primary link), `#F26A22` (hover)
- **Section Heading:** Font weight `700`, color `#FFFFFF`
- **Required Contact Details:** Monrovia, Liberia; `0881405259`; `ishotconcept01@gmail.com`; Instagram `@ishotconcept`; `www.ishotconcept.com`
- **Footer Message:** “Professional Photography | Creative Direction | Visual Storytelling”

### Links

**Standard Link (Body Context)**
- **Color:** `#0071BF`
- **Font:** `system-ui`, `15px`, weight `700`, line-height `20px`
- **Text Decoration:** `none`
- **Hover State:** Color to `#F26A22`, text decoration `underline`
- **Active State:** Color to `#D95718`

**Link (Light Background)**
- **Color:** `#0071BF`
- **Font:** `system-ui`, `14px`, weight `600`, line-height `28px`
- **Text Decoration:** `none`
- **Hover State:** Color to `#F26A22`, opacity `1`
- **Padding:** `0px` (inline links)

**Link (Dark Background)**
- **Color:** `#329AD3`
- **Font:** `system-ui`, `14px`, weight `600`, line-height `28px`
- **Text Decoration:** `none`
- **Hover State:** Color to `#F26A22`, opacity `1`
- **Padding:** `0px` (inline links)

## 5. Layout Principles

### Spacing System

**Base Unit:** `4px`

**Spacing Scale:**
- **4px** (`1×`): Micro spacing, internal component gaps
- **8px** (`2×`): Compact spacing, related elements
- **12px** (`3×`): Padding in compact components
- **16px** (`4×`): Standard component padding, margins between related sections
- **20px** (`5×`): Content padding, moderate vertical rhythm
- **24px** (`6×`): Large component margins
- **32px** (`8×`): Section content padding, card padding
- **40px** (`10×`): Major section breaks
- **48px** (`12×`): Large vertical rhythm between major sections
- **56px** (`14×`): Extra-large spacing for visual breathing room
- **72px** (`18×`): Hero section padding, full-width section padding, maximum content breathing

**Usage Context:**
- **4–8px:** Icon and button internal spacing, social icons, aperture motif spacing, tight form layouts
- **12–16px:** Button padding, input height (`40px` includes internal padding), form field margins, portfolio filter chips
- **20–32px:** Card padding, service container spacing, booking form spacing, moderate section padding
- **40–72px:** Section padding, hero areas, gallery breaks, mission/vision divisions, major layout transitions

### Grid & Container

**Max Width:** `1200px` (primary content container)  
**Column Strategy:** Flexible 12-column grid  
**Gutter Width:** `32px` between columns (responsive: `20px` on tablet, `16px` on mobile)  
**Section Pattern:**
  - Full-width sections with `#172D60` or `#FFFFFF` background
  - Content-constrained inner container with `max-width: 1200px`, `padding: 0 32px`, centered via `margin: 0 auto`
  - Vertical padding: `72px` top and bottom for major sections

**Container Breakpoints:**
- Desktop (1200px+): `max-width: 1200px`, `padding: 0 32px`, `margin: 0 auto`
- Tablet (768px–1199px): `max-width: 100%`, `padding: 0 20px`, adjust column count to 6–8
- Mobile (< 768px): `max-width: 100%`, `padding: 0 16px`, single or 2-column layout

**Recommended Page Structure:**
- **Hero:** Large photography background or visual montage, navy overlay, logo/nav, hook, primary CTA
- **Who We Are:** Premium brand summary and positioning
- **Vision & Mission:** Two-column or alternating dark/light sections
- **Services:** Six-card layout using service titles from iShot Concept
- **Portfolio / Visual Stories:** Masonry or editorial gallery grid
- **Why Choose Us:** Four proof cards: high-quality images, unique editing/retouching, fast delivery/client satisfaction, creative storytelling
- **CTA:** Dark navy section with orange booking button
- **Contact:** Phone, email, Instagram, location, and inquiry form

### Whitespace Philosophy

The design champions generous whitespace as a premium signal. Dark navy sections should breathe with ample padding, allowing photography and hero imagery to command attention without competing visual noise. Sections are clearly separated by substantial vertical rhythm (`48px`–`72px`), creating visual rest points and guiding user focus from emotional storytelling into service discovery and booking action. Within cards and containers, internal padding of `32px` ensures services, packages, testimonials, and contact details feel spacious and deliberately presented rather than cramped.

Negative space is treated as structural: it organizes hierarchy, improves scannability, and reinforces the premium perception of iShot Concept. Photography should never feel squeezed. Portraits, weddings, corporate images, reels, and creative concepts should be framed with breathing room, strong alignment, and confident image sizing.

### Border Radius Scale

- **0px:** Full-width sections, full-bleed backgrounds, hero imagery, dark gallery areas
- **3px:** Buttons, inputs, small cards, and component containers (primary radius for micro-interactions)
- **6px:** Larger card containers, portfolio images, image corners, service visuals
- **12px:** Gallery image overlays, featured portfolio moments, premium call-outs (rare; reserved for intentionally soft editorial sections)

## 6. Depth & Elevation

| Level | Treatment | Use |
|-------|-----------|-----|
| None (Flat) | `box-shadow: none`, `background: solid color` | Base containers, full-width sections, primary surfaces |
| Subtle | `box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08)` | Cards, containers resting state, service cards, minor component elevation |
| Standard | `box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12)` | Hovered cards, booking form panels, portfolio hover cards, modal overlays |
| Deep | `box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16)` | Modals, dropdowns, package overlays, focused contact panels |
| Extra Deep | `box-shadow: 0 12px 32px rgba(0, 0, 0, 0.20)` | Top-level modals, premium booking overlays, featured portfolio lightboxes |

**Shadow Philosophy**

Shadows in the iShot Concept system are restrained and purposeful, reflecting the premium studio-gallery environment. Rather than heavy interface decoration, subtle elevation is used to indicate interaction, depth, and focus. Most surfaces remain flat so the photography remains dominant. Shadow appears primarily on service cards, booking forms, portfolio hovers, and lightbox moments where content needs clear separation from the background. Shadows should never overpower faces, products, wedding images, or creative concepts; they serve as quiet visual guides that support the visual story.

## 7. Do's and Don'ts

### Do
- **Use iShot Navy (`#172D60`) as the primary background** for hero sections, headers, footers, and dark containers to establish the official brand identity.
- **Use Signature Orange (`#F26A22`) for primary booking actions** such as “Book Your Session,” “Let’s Create That Magic,” and “Contact Us.”
- **Use the official iShot Concept logo asset** rather than recreating the iSHOT mark with a font, since the logo is shape-based.
- **Feature strong photography prominently** with full-bleed hero images, editorial gallery grids, cinematic crops, and spacious portfolio sections.
- **Maintain consistent `40px` height for all interactive buttons and inputs** across the interface for predictable touch targets and visual rhythm.
- **Apply generous padding (`32px` to `72px`)** to section backgrounds to create breathing room and emphasize quality.
- **Use white (`#FFFFFF`) text on navy backgrounds** and iShot Navy (`#172D60`) text on light backgrounds for high contrast and legibility.
- **Employ `3px` border radius** as the standard rounding for buttons, inputs, and small components to maintain visual consistency.
- **Build service sections around iShot Concept’s actual offerings:** Birthday & Portrait Photography; Weddings, Renewals & Engagements; Corporate & Executive Shoots; Creative Direction & Concepts; Videography & Short Reels/Films; Studio & Outdoor Sessions.
- **Keep the tone premium and emotional:** moments, stories, identity, legacy, creativity, precision, style, and unforgettable experiences.

### Don't
- **Don't use the sample website’s original brand names, colors, or copy** anywhere in the final iShot Concept website.
- **Don't overuse all brand accent colors in the same section**; each section should usually rely on navy plus one primary accent.
- **Don't use orange as body text on white backgrounds** for long passages; reserve it for buttons, icons, badges, and short emphasis.
- **Don't apply shadows to full-width sections or large container backgrounds**; shadows are reserved for cards, forms, overlays, and interactive elements.
- **Don't reduce padding below `12px` in primary content areas** or button/input interiors; generous spacing is core to the premium aesthetic.
- **Don't use colored text on colored backgrounds** without sufficient contrast; stick to white on navy and navy/gray on light surfaces.
- **Don't apply border radius greater than `3px` to buttons and inputs** unless the component is intentionally editorial or image-focused.
- **Don't introduce random colors outside the defined brand palette** without documented semantic purpose.
- **Don't crowd the portfolio grid with too many images per row**; images need room to breathe and should feel curated.
- **Don't forget focus states for keyboard navigation**—all interactive elements require visible outline or color shift for accessibility.

## 8. Responsive Behavior

### Breakpoints

| Breakpoint | Width | Key Changes | Grid Columns |
|------------|-------|-------------|--------------|
| Mobile | 320px–767px | Single column, full-width containers, `16px` horizontal padding, reduced section padding (`40px` vert), stacked navigation, touch-optimized spacing, portfolio images stacked or 2-column thumbnails | 1–2 |
| Tablet | 768px–1199px | 2–3 column layout, `20px` horizontal padding, `56px` section padding, navigation condenses to mobile-style menu or simplified horizontal, service cards flow into 2 columns | 6–8 |
| Desktop | 1200px+ | 12-column grid, `32px` horizontal padding, `72px` section padding, full navigation visible, max-width container `1200px` centered, portfolio grids 3–4 columns | 12 |
| Large Display | 1400px+ | Same as desktop; optional: increase max-width to `1320px` and section padding to `80px` for premium views and larger gallery presentation | 12 |

### Touch Targets

**Minimum interactive element sizes:**
- **Buttons:** `40px` height (touch), `44px` recommended (WCAG AAA standard)
- **Links:** `44px` minimum tap target, achieved via padding around link text
- **Icon buttons:** `40px` × `40px` minimum including padding; icons themselves `18px`–`24px`
- **Form inputs:** `40px` minimum height, padding `12px` top/bottom, cursor-accessible text area
- **Navigation links:** `16px` padding horizontal, `20px` padding vertical on tablet, increased to `24px` on mobile if necessary
- **Portfolio filters:** Minimum `40px` height and clear active state using `#F26A22` or `#0071BF`
- **Form fields spacing:** Minimum `16px` vertical gap between labels and inputs, `20px` between form groups

### Collapsing Strategy

**Mobile (< 768px):**
- Full-width sections with no max-width constraints
- Single-column layout; grid items stack vertically
- Hero sections maintain image impact but reduce padding to `40px` vertical
- Hero copy should remain concise: hook, short value statement, one primary CTA
- Multi-column service cards collapse to single column
- Portfolio grid stacks vertically or becomes a 2-column thumbnail layout depending on image aspect ratio
- Navigation transforms to hamburger menu or hidden state with a visible “Book Your Session” action
- Buttons expand to full width or near-full width with `16px` margins
- Section padding reduces from `72px` to `40px` to conserve screen real estate
- Typography: Headings reduce 1–2 sizes if needed (e.g., H1 from `36px` to `28px`), maintain `16px` minimum for body

**Tablet (768px–1199px):**
- 2–3 column layout where applicable
- Hero sections maintain aspect ratio with `56px` vertical padding
- Service cards and feature boxes reflow to 2 columns; single featured items on full width
- Portfolio grid uses 2–3 columns with consistent gutters
- Navigation simplifies to horizontal menu or hybrid hamburger + primary booking link
- Buttons maintain `40px` height but may adjust width responsively
- Section padding `56px` vertical, `20px` horizontal padding

**Desktop (1200px+):**
- Full 12-column layout, all elements visible
- Max-width container `1200px` centered
- Hero sections display at full width with `72px` padding
- Multi-column service layouts display side-by-side
- Portfolio grids display 3–4 columns depending on content density
- Full horizontal navigation visible
- Hover states and interactive effects fully enabled
- Whitespace maximized for premium presentation

## 9. Agent Prompt Guide

### Quick Color Reference

- **Primary Background (Brand Gallery):** iShot Navy (`#172D60`)
- **Secondary Dark Surface:** Deep Studio Navy (`#101F44`)
- **Primary CTA Button:** Signature Orange (`#F26A22`)
- **Secondary Button:** Lens Blue (`#0071BF`)
- **Tertiary Accent:** Teal Blue (`#329AD3`)
- **Success / Positive State:** Creative Green (`#47A743`)
- **Ghost Button / Light Background:** White (`#FFFFFF`)
- **Light Section Background:** Soft Off-White (`#F7F9FC`)
- **Heading Text (on dark):** White (`#FFFFFF`)
- **Heading Text (on light):** iShot Navy (`#172D60`)
- **Body Text (on dark):** White (`#FFFFFF`)
- **Body Text (on light):** Medium Gray (`#545454`)
- **Secondary Text:** Light Gray (`#D8DEE8`) on dark, Gray (`#7A8595`) on light
- **Links (primary):** Lens Blue (`#0071BF`), hover to Signature Orange (`#F26A22`)
- **Links (dark background):** Teal Blue (`#329AD3`), hover to Signature Orange (`#F26A22`)
- **Card Background:** White (`#FFFFFF`) or Soft Off-White (`#F7F9FC`)
- **Borders:** Border Gray (`#D8DEE8`)
- **Dark Borders:** Dark Divider (`#223B78`)

### Iteration Guide

1. **Foundation Colors:** All dark backgrounds use `#172D60`; all primary text on dark is white (`#FFFFFF`). All light backgrounds use `#FFFFFF` or `#F7F9FC`; all primary headings on light are `#172D60`. This contrast rule is non-negotiable for accessibility and visual hierarchy.

2. **Call-to-Action Accent:** Every primary booking action uses `#F26A22` with white text. Use it for “Book Your Session,” “Let’s Create That Magic,” “Start a Concept,” and final CTA sections. Secondary actions use `#0071BF`. Tertiary highlights use `#329AD3`; success states use `#47A743`.

3. **Brand Copy Baseline:** The hero and primary brand message should reflect this statement: “Your moments deserve more than just pictures — they deserve to be remembered, felt, and celebrated.” Use this positioning: “iShot Concept is a premium photography brand based in Monrovia, dedicated to capturing timeless moments with creativity, precision, and style.”

4. **Service Content:** The service section must include these core services: Birthday & Portrait Photography; Weddings, Renewals & Engagements; Corporate & Executive Shoots; Creative Direction & Concepts; Videography & Short Reels/Films; Studio & Outdoor Sessions. Service cards should be visually consistent and photography-forward.

5. **Button & Input Standards:** All interactive elements default to `40px` height. Padding is `12px` vertical + `16px` horizontal for inputs and `10px`–`11px` vertical + `20px` horizontal for buttons. Border radius is always `3px` for buttons and inputs; `0px` for full-width sections.

6. **Typography Baseline:** All headings are system-ui, weight 700, with specific sizes: Display `48px`, H1 `36px`, H2 `28px`, H3 `24px`. All body text is system-ui weight 400, sizes `18px` (large), `16px` (standard), `14px` (small), `13px` (caption). Use the Botaniy-style script only for the “Concept” wordmark or selective signature accents.

7. **Spacing Rhythm:** Section padding is `72px` vertical (desktop) / `56px` (tablet) / `40px` (mobile). Component padding is `32px`. Button/input padding is `12px`–`20px`. Gap between form fields is `20px`. Gap between sections is `40px`–`72px`. Gutters on grid are `32px` (desktop), `20px` (tablet), `16px` (mobile).

8. **Layout Grid:** Desktop max-width `1200px` centered with `32px` side padding. Tablet max-width `100%` with `20px` padding. Mobile max-width `100%` with `16px` padding. Columns are 12 (desktop), 6–8 (tablet), 1–2 (mobile). All widths are percentage-based or max-width-constrained; no fixed pixel widths except for explicit component sizes.

9. **Content Sections & Contact:** The site should include Home, About, Services, Portfolio, Creative Direction, and Contact. Contact details must reflect iShot Concept: Monrovia, Liberia; `0881405259`; `ishotconcept01@gmail.com`; Instagram `@ishotconcept`; `www.ishotconcept.com`. Footer should include “Professional Photography | Creative Direction | Visual Storytelling.”

10. **Responsive Collapse:** Mobile layouts are single-column with full-width buttons and full-width cards. Forms stack vertically. Navigation collapses to hamburger, while “Book Your Session” remains highly visible. Hero sections reduce padding from `72px` to `40px`. Headings may reduce by 1 size (e.g., `36px` → `28px`) on mobile if space-constrained. Always maintain `16px` minimum font size for body text. Tablet uses 2–3 columns; desktop uses full 12-column grid. Breakpoint transitions: Mobile 320–767px, Tablet 768–1199px, Desktop 1200px+.
