# Database Migrations

This directory contains all database migrations for the Spacall platform.

## Migration Order

Migrations are executed in chronological order based on their timestamp prefix. The following groups of migrations exist:

### 1. Extension & Core Setup (2026_02_05_000001)

- **000001**: Enable PostGIS extension

### 2. User Management (2026_02_05_000002 - 000004)

- **000002**: Update users table for Spacall
- **000003**: Create user_addresses table
- **000004**: Create permission tables (Spatie)

### 3. Provider Management (2026_02_05_000005 - 000009)

- **000005**: Create providers table
- **000006**: Create therapist_profiles table
- **000007**: Create store_profiles table
- **000008**: Create provider_documents table
- **000009**: Create provider_locations table (partitioned)

### 4. Service Catalog (2026_02_05_000010 - 000012)

- **000010**: Create service_categories table
- **000011**: Create services table
- **000012**: Create provider_services pivot table

### 5. Booking System (2026_02_05_000013 - 000016)

- **000013**: Create bookings table
- **000014**: Create booking_assignments table
- **000015**: Create booking_locations table
- **000016**: Create booking_timeline table

### 6. Payment System (2026_02_05_000018 - 000023)

- **000018**: Create transactions table
- **000019**: Create wallets table
- **000020**: Create wallet_transactions table
- **000021**: Create payouts table
- **000022**: Create promo_codes table
- **000023**: Create promo_code_usages table

### 7. Reviews & Communications (2026_02_05_000024 - 000027)

- **000024**: Create reviews table
- **000025**: Create messages table
- **000026**: Create notifications table
- **000027**: Create device_tokens table

### 8. System Tables (2026_02_05_000028 - 000029)

- **000028**: Create app_settings table
- **000029**: Create audit_logs table

## PostGIS Triggers

Several tables use PostgreSQL triggers to auto-populate geography columns from latitude/longitude:

- `user_addresses` → `update_user_address_location()`
- `therapist_profiles` → `update_therapist_base_location()`
- `store_profiles` → `update_store_location()`
- `provider_locations` → `update_provider_location()`
- `booking_locations` → `update_booking_location()`

## Auto-Generated Fields

### UUIDs

- `users.uuid` - Auto-generated on creation
- `providers.uuid` - Auto-generated on creation
- `transactions.transaction_id` - Auto-generated on creation

### Booking Numbers

- `bookings.booking_number` - Auto-generated with format `SPC-YYYY-NNNNNN`

### Wallets

- Wallets are auto-created when users are created via trigger

### Timeline Tracking

- `booking_timeline` entries are auto-created when booking status changes

## Partition Management

### Provider Locations

The `provider_locations` table is partitioned by month. New partitions must be created monthly.

```sql
-- Create partition for March 2026
CREATE TABLE provider_locations_2026_03
PARTITION OF provider_locations
FOR VALUES FROM ('2026-03-01') TO ('2026-04-01');

CREATE INDEX provider_locations_2026_03_location_idx
ON provider_locations_2026_03 USING GIST (location);
```

Old partitions (older than 3 months) should be dropped to save space.

## Running Migrations

```bash
# Run all migrations
php artisan migrate

# Rollback last batch
php artisan migrate:rollback

# Rollback specific number of steps
php artisan migrate:rollback --step=5

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Check migration status
php artisan migrate:status
```

## Important Notes

1. **PostGIS Required**: Ensure PostgreSQL has PostGIS extension installed before running migrations
2. **Foreign Key Dependencies**: Migrations must run in order due to foreign key constraints
3. **Rollback Order**: Always rollback in reverse order to avoid foreign key conflicts
4. **Production**: Use zero-downtime deployment strategies for production migrations
