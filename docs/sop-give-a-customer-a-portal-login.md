# Give a customer a portal login

> Factory SOP. English because the ERP screens are in English.
>
> This is **not** the sales SOP (how David closes a new customer: MOQs,
> materials, colours). That one is still to be written. This one starts
> **after** the sale is closed and the company already exists in the CRM.
>
> URL the customer uses: `https://erp.anvfightgear.com/user/login`
> After login they land on `/my` (My orders). They never use `/customer/…`
> or `/tec_order/…`.

---

## Before you start

The portal only shows what is already on the company card. If any of this
is missing, stop and fix the CRM first. Do **not** create the login yet.

1. **Organisation** exists (`/customer/…` or the CRM list). Contact type
   includes **Customer**.
2. **Brands** are listed on that organisation, in the order they should
   appear on the order form.
3. Each brand has **products, colours, sizes and sales prices**. The
   customer **sees those prices**. A stale price is a commercial problem
   the moment they log in.
4. **Contact person** exists and **Works at** is that organisation.
5. You have an **email** that belongs to that person (for the Drupal
   account). One login per person. Several people at the same company
   each get their own login, all pointing at the same organisation
   through their own contact person.

Do **not** give this login to factory staff. Do **not** add the Customer
role to `david`, `lukpla`, `coo`, `manager` or `supervisor`.

---

## Steps

### 1. Create the Drupal user

`/admin/people/create`

| Field | What to put |
|---|---|
| Email | The contact person’s email |
| Username | Something they will remember. Not a factory username. |
| Password | Set one and send it by a channel that is not the same email, or use a one-time login link. |
| Status | Active |
| Roles | **Customer only.** Untick every factory role. |
| Contact person | The CRM person from the checklist above |

Save.

If **Contact person** is not on the form, the portal module is not
enabled — stop and tell whoever deploys the ERP.

### 2. Check the chain once

User → Contact person → Works at → organisation → brands → products.

If **Works at** is empty or points at another company, `/my` will show
the warning “This login is not linked to a company” and Place an order
will 403.

### 3. Tell the customer

- Login: `https://erp.anvfightgear.com/user/login`
- They will see **My orders** and **Place an order**.
- Open orders can still change quantities. **Confirm** sends the order
  to the factory and locks it. That is **not** payment.
- Status they see: Open → Pending payment → Confirmed → Ready to ship
  → Shipped (or Cancelled). They do not see factory stages
  (In Processing, Production Started, …).
- Date on the list is empty until Accounting Verified (payment seen).

### 4. What you do when they confirm

The order is the same sales order as a phone order (`tec_order`, number
like `CODE 26-001`). It appears on the production queue as a **grey**
row (Pending deposit). Production does **not** start.

When the deposit is in, click **→** on that row (or set Accounting
Verified on the order). The row leaves grey. The portal then shows
**Confirmed** and fills in **Date**.

---

## If something looks wrong

| What they see | Likely cause |
|---|---|
| Warning, no company | Contact person empty, or Works at empty |
| Empty catalogue | No brands on the organisation, or those brands have no sizes with prices |
| Factory screens (`/o`, `/stock`, other customers) | They have a factory role. Remove Customer from staff; remove factory roles from this login |
| 403 on Place an order | Not a portal customer, or the chain is broken |

To see what they see: log out of the factory account (or use a private
window). Do **not** add Customer to your own factory user.

---

## What this SOP does not cover

- Closing the sale (MOQs, materials, colours, mockups).
- Building the catalogue for a brand.
- Sending the proforma email (not wired yet).
- Moving an old WhatsApp customer onto the portal — same steps, once
  their organisation and prices are already in the ERP.
