# Oho Cafe

A PHP cafe POS with searchable, filterable menu categories and a live order panel. Enter the customer, seller and delivery names on the invoice, then print it in Khmer.

## Run locally

From this folder, start PHP's built-in development server:

```powershell
php -S 127.0.0.1:8000
```

Then open <http://127.0.0.1:8000> in a browser. The order and invoice number are stored in the PHP session. Printing opens the browser's print dialog; no payment is processed.
