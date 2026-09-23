Step-2 cheat (step 1 runs the oracle): complete client-side URL sync, history and pagination,
but the URL state is only applied in the browser after the scripts ran (an init callback reads
the query string). The server always renders the grid's default state, so shared URLs flash
from "all products" to the filtered view and crawlers/no-JS visitors get the wrong page.
Expected: step-2 PHPUnit (server state from URL, pagination via URL) and the Playwright
"nothing changes on load" checks fail → step 2 reward 0.
