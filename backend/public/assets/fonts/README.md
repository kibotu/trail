# Self-Hosted Fonts

This directory contains self-hosted Google Fonts for optimal performance and privacy.

## Fonts Included

### IBM Plex Sans (Headlines & Headings)
- **Usage**: All headings (h1-h6), logo, user names, and prominent text
- **Weights**: Regular (400), Medium (500), SemiBold (600), Bold (700)
- **Format**: WOFF2 (optimized for web)
- **Source**: [IBM Plex on GitHub](https://github.com/IBM/plex)

### Inter (Body Text & UI)
- **Usage**: Body text, buttons, forms, and all other UI elements
- **Weights**: Regular (400), Medium (500), SemiBold (600), Bold (700)
- **Format**: WOFF2 (optimized for web)
- **Source**: [Inter on GitHub](https://github.com/rsms/inter)

## Implementation

All fonts are loaded via `fonts.css` which is included in every page:

```html
<link rel="stylesheet" href="/assets/fonts/fonts.css">
```

### Font Stack

**Body/UI Elements:**
```css
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
```

**Headlines/Headings:**
```css
font-family: 'IBM Plex Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
```

## Performance

- **WOFF2 Format**: Modern, highly compressed format with excellent browser support
- **font-display: swap**: Ensures text remains visible during font loading
- **Selective Weights**: Only the weights actually used are included (400, 500, 600, 700)
- **Total Size**: ~716KB for all fonts (very reasonable for 8 font files)

## Browser Support

WOFF2 is supported by all modern browsers:
- Chrome 36+
- Firefox 39+
- Safari 12+
- Edge 14+

## Maintenance

To update fonts:
1. Download latest releases from GitHub
2. Extract WOFF2 files from the web/fonts directories
3. Replace files in `inter/` and `ibm-plex-sans/` directories
4. No CSS changes needed unless adding new weights

## Why Self-Hosted?

1. **Privacy**: No tracking from Google Fonts CDN
2. **Performance**: No external DNS lookups or HTTPS connections
3. **Reliability**: No dependency on third-party CDN availability
4. **Control**: Full control over font versions and caching
5. **GDPR Compliance**: No data sent to Google servers
