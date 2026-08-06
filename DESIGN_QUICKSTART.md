# Design System Quick Start

## TL;DR — Copy/Paste Templates

### Create a New Page (Hero + Content)

```blade
@extends('layouts.app')
@section('title', 'Page Title')
@section('page_title', 'Page Title')

@section('content')
    <div class="container-fluid">
        <!-- Hero / Intro -->
        <div class="mb-4">
            <h2>Main Title</h2>
            <p class="text-muted">Supporting text or description</p>
        </div>

        <!-- Content Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-icon mr-2"></i>
                            Section Title
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Content here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
```

---

### Display Key Metrics

```blade
<!-- Stat Card (large number) -->
<div class="card stat-card h-100">
    <div class="card-body">
        <p class="text-muted small text-uppercase mb-1">Metric Label</p>
        <h3 class="mb-0 text-primary">42</h3>
    </div>
</div>

<!-- Or: Inline Metrics Grid -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-label">Coverage</div>
        <div class="metric-value success">85%</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">Conflicts</div>
        <div class="metric-value danger">3</div>
    </div>
</div>
```

---

### Show Success/Warning/Error

```blade
<!-- Success -->
<div class="alert alert-success">
    <strong>Success!</strong> Operation completed
</div>

<!-- Warning -->
<div class="alert alert-warning">
    <strong>Attention!</strong> Please check...
</div>

<!-- Error -->
<div class="alert alert-danger">
    <strong>Error!</strong> Something failed
</div>

<!-- Info -->
<div class="alert alert-info">
    <strong>Info:</strong> Important note
</div>
```

---

### Quick Action Grid

```blade
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
        <a href="{{ route('route.name') }}" class="text-decoration-none">
            <div class="quick-action-card h-100">
                <i class="fas fa-icon fa-2x text-primary mb-3"></i>
                <h6 class="font-weight-600">Title</h6>
                <p class="text-muted small">Description</p>
            </div>
        </a>
    </div>
</div>
```

---

### Data Table

```blade
<div class="card border-0 shadow-sm">
    <div class="card-header bg-light border-0">
        <h5 class="card-title mb-0">
            <i class="fas fa-table mr-2"></i>
            Data
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Column 1</th>
                        <th>Column 2</th>
                        <th class="text-center">Column 3</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->value }}</td>
                        <td class="text-center">
                            <span class="badge badge-success">✓</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
```

---

### Status Badge

```blade
<!-- Single status -->
<span class="badge badge-{{ $item->status === 'success' ? 'success' : 'warning' }}">
    {{ ucfirst($item->status) }}
</span>

<!-- Multiple statuses (side by side) -->
<div>
    <span class="badge badge-success">✓ Created</span>
    <span class="badge badge-info">ℹ Pending</span>
    <span class="badge badge-warning">⚠ Needs Review</span>
</div>
```

---

### Forms

```blade
<div class="card border-0 shadow-sm">
    <div class="card-header bg-light border-0">
        <h5 class="card-title mb-0">Form Title</h5>
    </div>
    <div class="card-body">
        <form>
            <div class="form-group">
                <label for="name">Field Label</label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control @error('name') is-invalid @enderror"
                    placeholder="Enter value"
                />
                @error('name')
                    <small class="form-text text-danger">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="select">Dropdown</label>
                <select id="select" name="select" class="form-control" required>
                    <option value="">-- Select --</option>
                    <option value="1">Option 1</option>
                    <option value="2">Option 2</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-2"></i> Save
            </button>
        </form>
    </div>
</div>
```

---

## Color Variables (use in any CSS)

```css
/* Available CSS variables */
--primary: #2563eb      /* Blue */
--success: #10b981      /* Green */
--info: #3b82f6         /* Light blue */
--warning: #f59e0b      /* Amber */
--danger: #ef4444       /* Red */
--neutral-bg: #f9fafb   /* Light gray */
--neutral-border: #e5e7eb /* Borders */
--neutral-text: #374151  /* Dark text */

/* Usage */
.my-element {
    background-color: var(--primary);
    border-color: var(--neutral-border);
    color: var(--neutral-text);
}
```

---

## Common Utility Classes

### Spacing
```html
<!-- Margin bottom: mb-1 (0.5rem) to mb-5 (3rem) -->
<div class="mb-4">Content</div>

<!-- Padding: p-2 (0.5rem) to p-5 (3rem) -->
<div class="p-3">Content</div>

<!-- Margin top: mt-4 -->
<div class="mt-4">Content</div>
```

### Text
```html
<!-- Text colors -->
<p class="text-primary">Blue text</p>
<p class="text-success">Green text</p>
<p class="text-warning">Amber text</p>
<p class="text-danger">Red text</p>
<p class="text-muted">Gray text</p>

<!-- Text sizes -->
<p class="small">Small text</p>
<p class="lead">Large text</p>

<!-- Text alignment -->
<p class="text-center">Centered</p>
<p class="text-right">Right-aligned</p>

<!-- Font weight -->
<p class="font-weight-600">Semi-bold</p>
<p class="font-weight-700">Bold</p>
```

### Display
```html
<!-- Flex -->
<div class="d-flex justify-content-between align-items-center">
    <span>Left</span>
    <span>Right</span>
</div>

<!-- Grid -->
<div class="row">
    <div class="col-6">Half width</div>
    <div class="col-6">Half width</div>
</div>

<!-- Hide/Show -->
<div class="d-none">Hidden</div>
<div class="d-sm-none">Hidden on mobile</div>
<div class="d-lg-block">Shown on desktop</div>
```

---

## Icons + Colors (Quick Ref)

### Combine icon + color for maximum clarity

```blade
<!-- Red warning -->
<i class="fas fa-exclamation-circle text-danger"></i>

<!-- Green success -->
<i class="fas fa-check-circle text-success"></i>

<!-- Amber caution -->
<i class="fas fa-exclamation-triangle text-warning"></i>

<!-- Blue info -->
<i class="fas fa-info-circle text-info"></i>
```

### Common Icon Sizes
```blade
<!-- fa-lg (33% larger) -->
<i class="fas fa-icon fa-lg"></i>

<!-- fa-2x (2x) -->
<i class="fas fa-icon fa-2x"></i>

<!-- fa-3x (3x) -->
<i class="fas fa-icon fa-3x"></i>

<!-- Fixed width (for alignment in lists) -->
<i class="fas fa-fw fa-icon"></i>
```

---

## Responsive Classes

```blade
<!-- Show/hide per breakpoint -->
<div class="d-sm-none">Hidden on mobile</div>
<div class="d-lg-block">Shown on desktop</div>

<!-- Col sizes -->
<div class="col-12 col-sm-6 col-lg-3">
    Responsive grid: 
    - 12 cols on mobile
    - 6 cols on tablet
    - 3 cols on desktop
</div>

<!-- Text sizes -->
<h1 class="h3 h2-lg">Heading that shrinks on mobile</h1>
```

---

## Common Patterns

### Empty State
```blade
<div class="card border-0 shadow-sm bg-light">
    <div class="card-body text-center py-5">
        <i class="fas fa-inbox text-muted mb-3" style="font-size: 2.5rem;"></i>
        <p class="text-muted">No items yet</p>
        <a href="{{ route('create') }}" class="btn btn-sm btn-primary">
            <i class="fas fa-plus mr-1"></i> Create first item
        </a>
    </div>
</div>
```

### Loading State
```blade
<div class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
    </div>
</div>
```

### Confirmation Dialog
```blade
<div class="alert alert-warning" role="alert">
    <h5>Are you sure?</h5>
    <p>This action cannot be undone.</p>
    <form method="POST" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-sm btn-danger">
            Yes, delete
        </button>
        <button type="button" class="btn btn-sm btn-secondary">
            Cancel
        </button>
    </form>
</div>
```

---

## Do's and Don'ts

### ✅ DO
- Use semantic colors (red for danger, green for success)
- Combine icons with text
- Leave plenty of whitespace
- Group related items
- Use consistent spacing (multiples of 4px)
- Test on mobile before shipping

### ❌ DON'T
- Use more than 3 colors on one section
- Put critical info in hover states only
- Make buttons smaller than 44x44px
- Mix two different button styles
- Rely on color alone (always add text/icon)
- Nest more than 3 levels of menu items

---

## File Locations

```
resources/views/layouts/app.blade.php
└─ @push('css') contains all component styles

resources/views/dashboard.blade.php
└─ Example of new design system

resources/views/timetable/quality-improved.blade.php
└─ Complex example with multiple sections

config/adminlte.php
└─ Menu structure and navigation
```

---

## Need Custom Styles?

```blade
@push('css')
<style>
    .my-custom-class {
        background-color: var(--primary);
        padding: 1.5rem;
        border-radius: 6px;
    }

    /* Mobile override */
    @media (max-width: 768px) {
        .my-custom-class {
            padding: 1rem;
        }
    }
</style>
@endpush
```

---

## CSS Variable Reference

```css
/* Colors */
var(--primary)          #2563eb
var(--success)          #10b981
var(--info)             #3b82f6
var(--warning)          #f59e0b
var(--danger)           #ef4444
var(--neutral-bg)       #f9fafb
var(--neutral-border)   #e5e7eb
var(--neutral-text)     #374151

/* Use in any custom CSS */
.button-custom {
    background: var(--primary);
    border: 1px solid var(--neutral-border);
    color: var(--neutral-text);
}
```

---

## Troubleshooting

**Q: Colors look different**
→ Clear browser cache (Ctrl+Shift+Delete) and reload

**Q: Layout breaks on mobile**
→ Check if using `col-md-12` instead of `col-12`

**Q: Styles not applying**
→ Make sure using `@push('css')` inside `@section('content')`

**Q: Card looks different**
→ Remove `border-top`, `border-left` classes — use new `card` class only

---

## Resources

- **Design Brief**: `UI_UX_IMPROVEMENTS.md`
- **Component Reference**: `DESIGN_REFERENCE.md`
- **Implementation Guide**: `UI_UX_IMPLEMENTATION.md`
- **AdminLTE Docs**: [jeroennoten/Laravel-AdminLTE](https://github.com/jeroennoten/Laravel-AdminLTE/wiki)
- **Bootstrap Utilities**: [getbootstrap.com/docs](https://getbootstrap.com/docs/5.0)

---

**Last Updated**: August 6, 2026
**Version**: 1.0
**For**: Developers adding new features
