const base = 'https://sustainablecatalyst.com/wp-json/sustainable-catalyst-library/v1';

export async function searchLibrary(search, perPage = 10) {
  const url = new URL(`${base}/records`);
  url.searchParams.set('search', search);
  url.searchParams.set('per_page', String(perPage));
  const response = await fetch(url);
  if (!response.ok) throw new Error(`Library API ${response.status}`);
  return response.json();
}

const page = await searchLibrary('systems thinking');
console.log(page.items);
