# Reports — locked defaults (open questions closed)

These replace the former “Open Questions for Client” with product decisions.

## 1. Report Criteria filters

| Report | Criteria fields |
|--------|-----------------|
| **Sales Report By Item** | Date (or Date Between) · Customer (All) · **Item (All)** |
| **Sales Report By Totals** | Date (or Date Between) only · plus on-screen **Filter results** after run |

## 2. Manufacturer header when filter = All

- **All:** do **not** print a blank `Manufacturer:` line under the customer.
- Each manufacturer still has its **own group heading** above its lines.
- When a specific manufacturer is selected, print `Manufacturer: [name]` under the customer once.

## 3. Sales By Totals — Total column

- **Total** = stored `sales_orders.total` (saved at order time).
- Order formula in POS:  
  `Total ≈ Subtotal − Trade Discount + Freight + Miscellaneous + Tax`  
  (line item discounts already reduce **Subtotal**).
- Therefore **Total is not** `Subtotal − Item Discounts + Tax` alone.
- **Item Discounts** column = invoice `total_discount` if invoiced, else sum of order line discounts.

## 4. Sort order (locked)

| Report | Sort |
|--------|------|
| By Customer | Customer A–Z · then order date DESC · order # DESC |
| By Item | Customer A–Z · then date DESC · order # DESC · line # |
| By Categories | Customer A–Z · Category · Sub Category |
| By Totals | Order date DESC · order # DESC |
| By Stick Count | Invoice # DESC |
| By Manufacturer | Customer A–Z · Manufacturer A–Z · date · item code |
| Purchases Stick | Receipt date ASC |
| Purchases Item | Supplier A–Z · date · item |

Updated: 2026-08-06
