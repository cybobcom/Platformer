# src/admin – DEMO / override layer

Platformer's admin modules (cms, dashboard, tools) live in `src/capps/modules/` and are updated with `src/capps`.

This vendor folder has a higher priority than `capps` (see `$arrConf['cbinit']['vendors']`).
An instance puts only the files here that it wants to look different, with the same path as in `capps`:

```
src/capps/modules/dashboard/views/main.php    ← Platformer (updated)
src/admin/modules/dashboard/views/main.php    ← instance override (wins)
```

This works for direct calls (`/views/dashboard/main/`) and for views embedded via the CMS.

Updates never overwrite this folder. Overrides must keep the `CBAuth(...)` line of the original
and should be compared with the `capps` original after updates (security fixes).
