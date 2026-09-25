# Mago Assistant — Skills & Example Prompts

Overview of all available skills and example prompts to get the most out of the assistant.

## Configuration

### config_reader (read-only)
Read store configuration values by path.

```
What is my store name?
Show me the base URL
What is the default currency?
What locale is configured?
Show the value of web/secure/base_url
```

### config_writer (requires confirmation)
Modify store configuration values. Shows what will change before executing.

```
Change my store name to "My New Store"
Set the default currency to EUR
Update the store email to info@example.com
Set web/unsecure/base_url to https://mystore.com/
Disable the welcome message
```

### cache_manager (requires confirmation)
Manage Magento caches — flush all, flush specific types, or check status.

```
Flush all caches
Flush the config cache
Flush full_page cache
Show cache status
Which caches are enabled?
```

### indexer_manager (requires confirmation)
Manage indexers — reindex, check status, or change mode.

```
Show indexer status
Reindex the product prices
Reindex everything
Set all indexers to schedule mode
Which indexers need reindexing?
```

---

## Analytics

### sales_data (read-only)
Query orders, revenue, top products, and customer order history.

```
What was my revenue this month?
Show revenue for last week
What are my top 10 selling products?
Show recent orders
How many orders did I get today?
Look up order #100000042
Search orders for customer john@example.com
Which products get refunded the most?
Show orders above 500 euro from last month
```

### product_data (read-only)
Search products, check stock, and get product details.

```
How many products do I have?
Search for products with "jacket" in the name
Show me the product with SKU ABC-123
Which products are low on stock?
Show products under 10 stock
Find all disabled products
```

### stock_level / stock_level_msi (read-only)
Stock of one product by SKU. Only one of the two is offered: `stock_level` reads the catalog stock item on stores without Multi-Source Inventory, `stock_level_msi` shows quantity per source and salable quantity per stock when MSI is enabled.

```
How much stock does SKU 24-MB01 have?
Is SKU 24-MB01 in stock, and are backorders allowed?
Why is the salable quantity of SKU 24-MB01 lower than its stock?
```

### customer_data (read-only)
Customer counts, recent signups, top spenders, and lookups.

```
How many customers do I have?
Show recent signups from this week
Who are my top 10 spenders?
Look up customer john@example.com
How many customers signed up this month?
```

---

## Content

### content_generator (requires confirmation)
Generate product descriptions and meta tags using AI. Reviews content before saving.

```
Generate a product description for SKU ABC-123
Write meta tags for SKU JACKET-BLK
Generate a short description for SKU SHOE-42 in a casual tone
Rewrite the description for SKU ABC-123, make it more persuasive
Generate meta tags for SKU ABC-123 with keywords "summer" and "sale"
```

### cms_data (requires confirmation for updates)
Manage CMS pages and blocks — list, view, and update content.

```
List all CMS pages
Show me the homepage content
Show CMS block "footer_links"
List all CMS blocks
Update the "about us" page content
```

---

## Navigation

### admin_navigator (read-only)
Find direct links to admin pages, orders, products, and customers.

```
Where do I find the shipping settings?
Link to the tax configuration
How do I get to the catalog price rules?
Show me the link to order #100000042
Where is the email templates page?
```

---

## Combined Prompts

The assistant can combine multiple skills in a single request:

```
Change my store name to "New Store" and flush the config cache
What was my revenue this month and who are my top 5 customers?
Show me SKU ABC-123 and generate new meta tags for it
Reindex everything and flush all caches
How many orders did I get today and what's my revenue?
```

---

## Write Actions & Confirmation

Skills marked **requires confirmation** will show exactly what will happen before executing.
You'll see the tool name, parameters, and values — then choose **Confirm** or **Reject**.

Read-only skills execute automatically without confirmation.
