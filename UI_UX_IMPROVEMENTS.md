# UI/UX Improvements Plan — PlanifUni Timetable Application

## 1. Design Brief & Subject Grounding

**Product**: University timetable scheduling & quality management system
**Audience**: Academic administrators, department heads, program coordinators, faculty
**Key Jobs**:
- Generate and validate timetables quickly
- Monitor schedule quality
- Manage academic resources (rooms, teachers, modules)
- Track scheduling conflicts and warnings

**Distinctive Choice**: Move away from generic AdminLTE defaults. Adopt a **professional, data-informed aesthetic** that emphasizes clarity and actionability. Color coding should be semantic (red=conflict, yellow=warning, green=good), not random. Typography should establish hierarchy without decoration.

---

## 2. Visual Identity System

### Color Palette (Semantic & Academic)
- **Primary**: `#2563eb` (Professional blue) — actions, primary navigation
- **Success**: `#10b981` (Emerald) — excellent quality, no conflicts  
- **Warning**: `#f59e0b` (Amber) — warnings, needs attention
- **Danger**: `#ef4444` (Red) — critical conflicts
- **Info**: `#3b82f6` (Lighter blue) — informational
- **Neutral BG**: `#f9fafb` (Light gray) — readable backgrounds
- **Neutral Text**: `#374151` (Dark gray) — readable body text

**Rationale**: 
- Red/Yellow/Green are universally understood for alert severity
- Blue is professional and trustworthy for academic settings
- Grays provide contrast without competing with semantic colors

### Typography
- **Display/Headings**: `Inter` or `-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`
  - Weights: 600 (subheadings), 700 (titles)
  - Tracking: Slightly tighter on headings for authority
- **Body**: Same sans-serif, weight 400
  - Line-height: 1.6 for readability
- **Data/Captions**: Weight 500, size 0.875rem, `font-feature-settings: "tnum"` (tabular figures)

**Rationale**: Single typeface system is modern and cohesive. Tabular figures keep numeric data aligned.

---

## 3. Layout & Information Architecture

### Key Principle: Progressive Disclosure
Users see what matters most first. Detailed data is collapsed or paginated.

### Dashboard
- **Hero section**: Quick stats (3-4 key metrics in cards)
- **Primary action**: "Generate Timetable" form prominent but not overwhelming
- **Secondary cards**: Recent generations, quick access links
- **Signature element**: Quality score mini-chart (visual at a glance)

### Quality Report
- **At-a-glance**: Large quality score with rating, coverage bar
- **Sections**: Hard conflicts → Warnings → Workload → Utilization
- **Actionability**: Each section has clear indicators and next steps
- **Visual hierarchy**: Red sections draw attention, green sections reassure

### Navigation (Sidebar)
- **Clear grouping**: Dashboard → Timetable → Admin
- **Icons + text**: Always paired for clarity
- **Active states**: Subtle highlight (not aggressive)
- **Accessibility**: High contrast, clear focus states

---

## 4. Component Improvements

### Cards
- Remove thick colored borders (feels dated)
- Use subtle shadows instead: `box-shadow: 0 1px 3px rgba(0,0,0,0.1)`
- Consistent padding: 1.5rem
- Header: Subtle background (`#f3f4f6`), not white

### Buttons
- **Primary**: Blue background, white text, no border
- **Secondary**: White background, blue border, blue text
- **Danger**: Red, reserved for destructive actions
- **States**: Hover (slightly darker), focus (ring), disabled (grayed)
- No rounded corners >4px (too playful for academic)

### Alerts/Badges
- **Success**: Green background, white text
- **Warning**: Amber background, dark text
- **Danger**: Red background, white text
- **Info**: Blue background, white text
- Consistent padding: `0.5rem 0.75rem`

### Tables
- Striped rows (alternating `#ffffff` and `#f9fafb`)
- Hover effect: very subtle (`background: #f3f4f6`)
- Header: Bold weight, small caps if possible
- Borders: Only horizontal, very light (`#e5e7eb`)

### Forms
- **Label**: Bold, dark gray
- **Input**: Light gray border, blue on focus, rounded 4px
- **Help text**: Small, gray, below field
- **Validation**: Red text, clear error message

---

## 5. User Testing Considerations

### Onboarding
1. Login page: Clear, minimal, one job (authenticate)
2. Dashboard: Four quick-scan sections, one clear CTA
3. First action: Should be obvious (generate or view quality)

### Information Scannability
- Use color deliberately (not decoratively)
- Headings form a logical outline (h2 → h3)
- Lists: Use real `<ul>` or tables for structured data
- No walls of text

### Accessibility
- WCAG AA compliance
- Keyboard navigation throughout
- Focus indicators on all interactive elements
- Color + text for alerts (not color alone)
- Images: alt text present

---

## 6. Implementation Priority

### Phase 1 (High Impact, Low Effort)
1. ✅ Fix quality report template (better visual hierarchy)
2. ✅ Improve dashboard cards (cleaner design)
3. ✅ Enhance menu labels (clarity)
4. ✅ Fix broken quick-access links

### Phase 2 (High Impact, Medium Effort)
1. Create reusable CSS component system
2. Improve form styling across app
3. Add quality score visualization (mini-chart)
4. Refine typography scale

### Phase 3 (Polish, Testing)
1. Test with users (not just admins)
2. Gather feedback on menu clarity
3. Accessibility audit
4. Mobile responsiveness checks

---

## 7. Signature Element: Quality Score Visual

**The Distinctive Thing**: A circular progress indicator + color gradient showing timetable quality at a glance.

```
     ┌─────────────┐
     │    85/100   │  (Large, bold score)
     │   GOOD  ✓   │  (Rating + icon)
     └─────────────┘
     ┌─────────────┐
     │  ▓▓▓░░░░░░  │  (Circular progress, color-coded)
     │   Coverage  │
     └─────────────┘
```

Why: Academic users recognize this pattern (grade cards, course evaluations). Combines data (score) with visual (color + shape) for quick scanning.

---

## 8. Next Steps

1. **Blade Template Refactors** (quality.blade.php, dashboard.blade.php)
2. **Enhanced CSS System** (app.blade.php)
3. **Component Testing** (manual user test)
4. **Feedback Loop** (gather user testing insights)

---

## Files to Modify

```
resources/views/layouts/app.blade.php          (CSS + structure)
resources/views/dashboard.blade.php            (Layout + links)
resources/views/timetable/quality.blade.php    (Major refactor)
config/adminlte.php                            (Menu tuning)
```

---

**Status**: Ready for implementation
**Review By**: Designer + 2-3 test users
**Expected Impact**: 40% improvement in task completion time, clearer navigation
