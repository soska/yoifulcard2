Yoiful (Laravel rewrite). Spec: [../yoifulcard/REWRITE-PRD.md](../yoifulcard/REWRITE-PRD.md). Build plan: [../yoifulcard/REWRITE-PLAN.md](../yoifulcard/REWRITE-PLAN.md).

## Demo data

Local only (the seeder refuses to run in production). Safe to run more than once; it never wipes the database.

```bash
php artisan db:seed --class=DemoSeeder
```

Every login uses the password `yoiful-test-123`:

| Login                   | Access                                                                 |
| ----------------------- | ---------------------------------------------------------------------- |
| `armando@yoiful.test`   | Superadmin, owner of Café La Esquina, manager at Panadería Dulce Hogar |
| `owner2@yoiful.test`    | Owner of Panadería Dulce Hogar (card usage above 80%)                  |
| `employee@yoiful.test`  | Employee at Panadería Dulce Hogar                                      |
| `suspended@yoiful.test` | Owner of Bicis del Norte (suspended)                                   |

The seeder prints a few public card URLs (`/c/{token}`) when it finishes.
