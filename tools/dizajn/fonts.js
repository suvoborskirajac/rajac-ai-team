const fs = require('fs'), path = require('path');
module.exports = function (NM) {
  const base = NM + '/@fontsource';
  const slug = f => f.toLowerCase().replace(/\s+/g, '-');
  function css(url) {
    const u = new URL(url); const fams = u.searchParams.getAll('family'); let out = '';
    for (const fam of fams) {
      const name = fam.split(':')[0]; const dir = path.join(base, slug(name));
      if (!fs.existsSync(dir)) { out += `/* нема ${name} */\n`; continue; }
      const files = fs.readdirSync(dir).filter(f => /^\d{3}(-italic)?\.css$/.test(f));
      for (const f of files) out += fs.readFileSync(path.join(dir, f), 'utf8').replace(/url\(\.\/files\//g, `url(https://lf.local/${slug(name)}/files/`);
    }
    return out;
  }
  return async (route) => {
    const u = route.request().url();
    if (u.startsWith('https://fonts.googleapis.com/')) return route.fulfill({ body: css(u), contentType: 'text/css' });
    if (u.startsWith('https://lf.local/')) {
      const p = path.join(base, decodeURIComponent(u.slice('https://lf.local/'.length)));
      if (fs.existsSync(p)) return route.fulfill({ path: p, contentType: 'font/woff2' });
      return route.fulfill({ status: 404, body: '' });
    }
    if (/^https:\/\/(www\.)?piorajac\.rs\//.test(u)) return route.continue();
    return route.fulfill({ status: 404, body: '' });
  };
};
