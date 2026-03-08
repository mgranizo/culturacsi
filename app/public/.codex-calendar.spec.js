const { test } = require('playwright/test');

test('inspect calendar overlay after month changes', async ({ page }) => {
  await page.goto('http://localhost:10010/calendar/', { waitUntil: 'networkidle' });

  async function read(label) {
    return page.evaluate((label) => {
      const overlay = document.querySelector('.calendar-hero-month-overlay');
      const span = overlay && overlay.querySelector('span');
      const hero = overlay && overlay.parentElement;
      function rect(el) {
        if (!el) return null;
        const r = el.getBoundingClientRect();
        return { left: r.left, right: r.right, top: r.top, bottom: r.bottom, width: r.width, height: r.height };
      }
      return {
        label,
        url: location.href,
        text: span ? span.textContent.trim() : null,
        hero: rect(hero),
        overlay: rect(overlay),
        span: rect(span),
        spanInline: span ? span.getAttribute('style') : null,
        overlayInline: overlay ? overlay.getAttribute('style') : null,
      };
    }, label);
  }

  console.log(JSON.stringify(await read('initial'), null, 2));
  await page.selectOption('select[name="ev_m"]', '3');
  await page.waitForTimeout(1500);
  console.log(JSON.stringify(await read('after-march'), null, 2));
  await page.selectOption('select[name="ev_m"]', '9');
  await page.waitForTimeout(1500);
  console.log(JSON.stringify(await read('after-september'), null, 2));
});
