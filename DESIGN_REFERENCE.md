# Design Reference Guide

## Color System at a Glance

### Semantic Color Usage

```
┌──────────────────────────────────────────────────────────┐
│ SUCCESS #10b981 (Emerald)                                │
├──────────────────────────────────────────────────────────┤
│ Usage: Excellent quality, no conflicts, normal workload   │
│ Components: Stat cards, success badges, green progress    │
│ Example: "85/100" score (if >= 90)                        │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ WARNING #f59e0b (Amber)                                  │
├──────────────────────────────────────────────────────────┤
│ Usage: Warnings, needs attention, overload               │
│ Components: Yellow badges, warnings section              │
│ Example: "2 warnings detected" section                   │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ DANGER #ef4444 (Red)                                     │
├──────────────────────────────────────────────────────────┤
│ Usage: Critical conflicts, major issues                  │
│ Components: Conflict items, red badges                   │
│ Example: "Teacher double-booked" in conflicts section    │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│ PRIMARY #2563eb (Blue)                                   │
├──────────────────────────────────────────────────────────┤
│ Usage: Primary actions, navigation, informational        │
│ Components: Buttons, links, primary badges              │
│ Example: "View more" buttons, menu active state         │
└──────────────────────────────────────────────────────────┘
```

---

## Component Library

### Buttons

#### Primary Button
```html
<button class="btn btn-primary">
  <i class="fas fa-magic mr-2"></i> Generate
</button>
```
**Style**: Blue background, white text, 4px radius
**Use when**: Main action user should take
**States**: Hover (darker blue), Focus (ring), Disabled (grayed)

#### Secondary Button
```html
<button class="btn btn-outline-primary">
  View Details
</button>
```
**Style**: White background, blue border/text, 4px radius
**Use when**: Alternative action
**States**: Same as primary

#### Danger Button
```html
<button class="btn btn-danger">
  <i class="fas fa-trash mr-2"></i> Delete
</button>
```
**Style**: Red background, white text
**Use when**: Destructive action (delete, remove)
**States**: Hover (darker red), Focus (ring)

---

### Cards

#### Standard Card
```html
<div class="card">
  <div class="card-header bg-light border-0">
    <h5>Title</h5>
  </div>
  <div class="card-body">
    Content here
  </div>
</div>
```
**Style**: Light gray header, subtle shadow, 1px border
**Padding**: 1.5rem on body

#### Stat Card
```html
<div class="card stat-card">
  <div class="card-body">
    <p class="text-muted small">LABEL</p>
    <h3 class="text-primary">42</h3>
  </div>
</div>
```
**Style**: Blue left border (3px), subtle gradient background
**Use when**: Displaying key metrics

#### Quick Action Card
```html
<div class="quick-action-card">
  <i class="fas fa-calendar fa-2x text-primary"></i>
  <h6>View Timetable</h6>
  <p class="text-muted small">Current sessions</p>
</div>
```
**Style**: Icon + title + description, hover lifts card
**Use when**: Navigation options

---

### Alerts

#### Success Alert
```html
<div class="alert alert-success">
  <strong>Success!</strong> Operation completed
</div>
```
**Colors**: Green background + border, green left accent
**Use when**: Positive confirmation

#### Warning Alert
```html
<div class="alert alert-warning">
  <strong>Attention!</strong> Please review...
</div>
```
**Colors**: Amber background + border, amber left accent
**Use when**: Needs attention

#### Danger Alert
```html
<div class="alert alert-danger">
  <strong>Error!</strong> Something went wrong
</div>
```
**Colors**: Red background + border, red left accent
**Use when**: Critical error

---

### Badges

```html
<span class="badge badge-success">✓ Good</span>
<span class="badge badge-warning">⚠ Warning</span>
<span class="badge badge-danger">✕ Critical</span>
<span class="badge badge-info">ℹ Info</span>
```

**Sizes**: 0.5rem top/bottom, 0.75rem left/right
**Font**: 500 weight, 0.85rem size
**Use when**: Quick status indicator

---

## Layout Patterns

### Hero Section (Quality Report)
```
┌────────────────────────────────┐
│      85/100                     │  Large number
│      GOOD  ✓                    │  Rating + icon
│                                 │
│  Excellent timetable...         │  Short description
│                                 │
│  ┌─────┬──────┬────┬─────┐    │
│  │85%  │16/18 │ 0  │  2  │    │  Metrics grid
│  └─────┴──────┴────┴─────┘    │
└────────────────────────────────┘
```

### Section with Header
```
┌────────────────────────────────┐
│ ⚠ WARNINGS (2)                  │  Icon + title + count
├────────────────────────────────┤
│ ❌ Teacher overload              │  Issue type (bold)
│    High workload: 24h/week       │  Message
│                                 │
│ ❌ Long consecutive             │
│    Prof. Bob has 4 consecutive   │
└────────────────────────────────┘
```

### Table Layout
```
┌─────────────────────────────────────┐
│ TEACHER      │ SESSIONS │ HOURS/WK  │ STATUS
├─────────────────────────────────────┤
│ Dr. Alice    │    8     │    16h    │ ✓ Normal
│ Prof. Bob    │   10     │    20h    │ ⚠ High
│ Carol White  │    6     │    12h    │ ✓ Normal
└─────────────────────────────────────┘
```

---

## Typography Hierarchy

### Page Title
- Font: System sans-serif
- Weight: 700 (bold)
- Size: 2rem (32px)
- Color: #374151 (dark gray)
- Usage: Main heading for page

### Section Header
- Font: System sans-serif
- Weight: 600 (semi-bold)
- Size: 1.1rem (18px)
- Color: #374151
- Usage: Card headers, section dividers
- Note: Always paired with icon

### Subsection Title
- Font: System sans-serif
- Weight: 600
- Size: 0.95rem (15px)
- Color: #374151
- Usage: Small section titles

### Body Text
- Font: System sans-serif
- Weight: 400
- Size: 0.95rem to 1rem (15-16px)
- Color: #374151
- Line-height: 1.6 (readable)

### Small/Caption
- Font: System sans-serif
- Weight: 500
- Size: 0.85rem (13px)
- Color: #6b7280 (medium gray)
- Usage: Labels, helper text

---

## Spacing System

All spacing follows a 4px base unit (or 0.5rem in CSS):

```
xs: 0.5rem (8px)     → Tight spacing (badges, pills)
sm: 1rem (16px)      → Component padding
md: 1.5rem (24px)    → Card padding, section gaps
lg: 2rem (32px)      → Page sections
xl: 3rem (48px)      → Major sections, margins
```

### Card Spacing
```
Card body:    1.5rem padding (all sides)
Card header:  border-bottom, 1px solid
Gap between:  1rem between card sections
```

---

## Icons + Colors

### Icon Usage Guide

**Primary Actions**
```html
<i class="fas fa-magic"></i>           <!-- Generate -->
<i class="fas fa-plus-circle"></i>     <!-- Add -->
<i class="fas fa-calendar"></i>        <!-- Timetable -->
```

**Status Indicators**
```html
<i class="fas fa-check-circle"></i>    <!-- Success/OK -->
<i class="fas fa-exclamation-triangle"></i> <!-- Warning -->
<i class="fas fa-exclamation-circle"></i>   <!-- Error -->
```

**Navigation**
```html
<i class="fas fa-home"></i>            <!-- Dashboard -->
<i class="fas fa-list-ul"></i>         <!-- List -->
<i class="fas fa-cog"></i>             <!-- Settings -->
```

### Icon Sizing
- Navigation: `fa-fw` (fixed width, 1.25rem)
- Section headers: `fa-lg` (1.33rem)
- Hero: `fa-2x` (2rem)
- Large display: `fa-3x` (3rem)

---

## Dark Mode Considerations

Currently: Light theme only
Future: Can add dark theme by:
1. Creating `--dark-primary`, `--dark-bg` CSS variables
2. Adding `.dark-mode` class selector
3. Testing contrast ratios

---

## Responsive Breakpoints

```css
/* Desktop (1920px+) */
.quick-action-card { grid-template-columns: repeat(4, 1fr); }

/* Laptop (1366px) */
.quick-action-card { grid-template-columns: repeat(3, 1fr); }

/* Tablet (768px) */
.quick-action-card { grid-template-columns: repeat(2, 1fr); }
.card-body { padding: 1rem; }

/* Mobile (375px) */
.quick-action-card { grid-template-columns: repeat(1, 1fr); }
.page-header h1 { font-size: 1.5rem; }
```

---

## Before & After Comparison

### Dashboard

**BEFORE** (Generic AdminLTE)
- Small boxes with icons
- No clear visual hierarchy
- Links to nowhere (dashboard route)
- Generic styling

**AFTER** (Improved)
- Clean stat cards with values
- Clear H2 → card → action hierarchy
- Links to real routes (timetable, quality)
- Professional appearance

### Quality Report

**BEFORE**
- Long page with many sections
- Mixed styling
- Hard to scan
- Repeated patterns

**AFTER**
- Hero section shows score immediately
- Color-coded sections (red/yellow/green)
- Scannable with clear headers
- Consistent component system

### Menu

**BEFORE**
- Generic "Gestion" dropdown
- No clear organization
- 8+ menu items jumbled

**AFTER**
- Grouped by workflow (Timetable → Admin)
- Clear sub-sections
- Reduced cognitive load
- Logical navigation

---

## Testing Checklist for Designers

- [ ] Color contrast ratios pass WCAG AA
- [ ] All interactive elements have focus states
- [ ] Spacing is consistent (multiple of 4px)
- [ ] Typography hierarchy is clear
- [ ] Icons are used consistently
- [ ] Mobile layout doesn't break
- [ ] Buttons are large enough (44px minimum)
- [ ] Forms have proper labels
- [ ] Error messages are clear
- [ ] Success states are clear

---

## Common Gotchas

### Don't
- ❌ Mix different button styles on one page
- ❌ Use color alone to communicate (always add icon/text)
- ❌ Put critical actions in menus (put them in hero)
- ❌ Use skewed or rotated text
- ❌ Nest more than 3 levels of dropdowns

### Do
- ✅ Use consistent spacing
- ✅ Group related items together
- ✅ Put primary action first
- ✅ Use semantic colors
- ✅ Test on real devices

---

**Last Updated**: August 6, 2026
**Version**: 1.0
**Maintained By**: Design Team
