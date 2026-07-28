# Course Discovery frontend design

## Reference and scope

The public `[course_discovery]` interface uses the exported Stitch design at
`app/plugins/course-discovery/stitch_university_course_discovery_portal` as a
visual reference. The export is not a runtime dependency. Its Tailwind setup,
remote font and icon requests, sample records, and framework-style JavaScript
are intentionally not copied into the plugin.

The implementation remains the existing PHP shortcode template, a stylesheet
scoped below `.course-discovery`, and a small vanilla JavaScript enhancement.
It is designed to sit within a wide range of WordPress themes without changing
global headings, forms, buttons, links, or page layout.

The generated page uses a native full-width Group block and the shortcode root
carries WordPress's `alignfull` class. The stylesheet fills that parent width
without forcing viewport width or using negative breakout margins. Its layout
responds to the `.course-discovery` component's inline size with container
queries, with equivalent viewport media queries as a compatibility fallback.
This preserves the theme's ownership of content width, header, and page title;
the plugin does not target theme-specific selectors outside
`.course-discovery`. A theme or manually selected page template can therefore
choose a narrow, wide, or full-width slot without plugin CSS escaping it.

## Supported data mapping

| Design area | Course Discovery data |
| --- | --- |
| Card heading | Course title |
| Card summary | Course excerpt / short description |
| Category chips | Assigned Course Categories |
| Institution row | Related Providers |
| Location row | Locations derived from related Providers |
| Calendar row | Canonical Course start months |
| Person row | Related Instructors |
| Price footer | User-friendly amount and currency for GBP, EUR, or USD |

All values are prepared by `CourseResultPresenter` and escaped in the template.
Money is formatted in the presentation layer with the explicit symbols `£`,
`€`, and `$`; the ISO code remains part of prepared data where the symbol alone
could be ambiguous. The template performs no metadata, taxonomy, request, or
search queries.

## Visual system

The adapted UI follows the export's main visual decisions:

- deep navy `#0a1f44` hero and primary controls;
- amber `#f59e0b` search action and focus indicator;
- warm white cards on a `#f8f9fa` catalogue surface;
- a maximum content width of 1280px with 24px desktop gutters;
- system typography beginning with Inter when it is already available;
- rounded search, filter, card, chip, and pagination shapes;
- soft ambient card shadows with a reduced-motion-safe hover lift;
- a flexible metadata grid that collapses from three columns to one according
  to component width;
- a sticky wide-layout filter panel and a full-height drawer when the viewport
  is below 768px.

Frontend assets use the plugin version as their cache key. Visual releases must
increment that version so updated shortcode markup is never paired with stale
CSS or JavaScript from a previous browser cache.

The wide result column stays primary while filters remain visible at the right.
At narrow component widths, results and the inline filter panel stack even when
the surrounding viewport is wide. When the viewport itself is below 768px,
JavaScript hides that panel until the filter control is activated and presents
it as a modal drawer. Without JavaScript the same native GET form remains in the
document and usable after the results.

## Interaction and accessibility

Filtering remains a server-rendered GET request. Checkbox state, search text,
page size, pagination arguments, OR within a filter, and AND across filters are
unchanged. Active filter chips are ordinary links that remove one selected
canonical value and reset pagination to the first page.

Filter groups use native `details`, `summary`, `fieldset`, `legend`, labels, and
checkboxes. The mobile drawer adds Escape handling, focus return, a keyboard
focus loop, a labelled close control, and modal semantics only while open.
The filter link still targets the native panel if JavaScript is unavailable.
Visible focus styles use the amber accent, and reduced-motion preferences remove
non-essential animation.

## Intentionally unsupported reference features

The following Stitch concepts are not implemented because the current task or
content model does not support them:

- ratings, favourites, featured labels, and comparison controls;
- provider logos, course images, and decorative institution branding;
- duration, schedule, delivery mode, study level, or rolling enrolment;
- application, enrolment, save, and Course detail actions;
- price ranges, multiple offers, conversion, or exchange rates;
- sort choices beyond the existing deterministic result order;
- result counts per individual filter option.

Adding any of these later requires a separate content/search requirement rather
than a presentation-only change.
