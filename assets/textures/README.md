# Light Oak Texture

`light-oak-v1.webp` is a generated material texture for public primary buttons and
the quote banner. Created with the built-in image generation tool on 2026-10-05;
resized to 1200 x 480 and encoded with Sharp as WebP, quality 64, effort 6.
Size: 25,122 bytes. No embedded source metadata, external requests or font assets.

Generation prompt:

> Use case: photorealistic-natural. Asset type: reusable website material texture for premium Scandinavian furniture brand CTA buttons and a full-width contact banner. Generate a seamless tileable light natural oak veneer texture, photographed perfectly straight-on with flat even diffuse studio lighting. Fine subtle long horizontal wood grain, refined quarter-sawn oak, soft pale honey and blond champagne hues, very low contrast so dark charcoal UI lettering is clearly readable over it. Real finely sanded matte wood, natural delicate pores, clean understated luxury. Texture fills entire canvas edge to edge, preferably wide landscape format. No planks, no seams, no border, no knots, no strongly dark grain, no orange cast, no objects, no text, no logo, no watermark, no perspective, no vignette, no cast shadows, no baked highlights. It must look like actual oak, not a CSS gradient.

The banner uses a 30% neutral overlay; buttons use multiply blending and separate
hover colors. `tests/design-http.cjs` checks the asset budget and minimum text
contrast across decoded texture pixels. Plain background colors remain fallbacks.
