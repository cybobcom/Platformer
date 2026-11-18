# src/agent – DEMO / override layer

Example of an instance-specific layer: `modules/console/views/main.php` overrides the console start page
from `src/capps/modules/console/` and shows the user's agents (class `Agent` is provided by the instance).

Priority: `custom` > `agent` > `admin` > `capps`. Updates (`src/capps`) never overwrite this folder.
See `src/admin/README.md` for how overrides work.
