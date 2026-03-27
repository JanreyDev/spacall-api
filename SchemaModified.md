# Database Schema Documentation
## Spacall - On-Demand Wellness Platform

**Version:** 1.0.0  
**Last Updated:** 2026-02-03  
**Database:** PostgreSQL 15 + PostGIS 3.3  

---

## 1. Overview

The Spacall database is designed to support a two-sided marketplace connecting customers with massage therapists and wellness centers. The schema emphasizes:

- **Geospatial capabilities** (PostGIS for location-based matching)
- **Flexible booking system** (home service + in-store)
- **Multi-role user management** (customers, therapists, store owners, admins)
- **Audit trails** (soft deletes, timestamps)
- **Performance** (proper indexing, partitioning for large tables)

---

## 2. Entity Relationship Diagram (ERD) Description

### **Core Entities:**

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USER MANAGEMENT                              │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    │               │               │
              ┌─────▼─────┐   ┌────▼────┐   ┌─────▼──────┐
              │   users   │   │ roles   │   │permissions │
              └─────┬─────┘   └────┬────┘   └─────┬──────┘
                    │              │               │
              ┌─────▼─────────────────────────────▼─────┐
              │        model_has_roles                   │
              │     model_has_permissions                │
              └──────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      PROVIDER MANAGEMENT                             │
└─────────────────────────────────────────────────────────────────────┘
                    │
    ┌───────────────┼───────────────┐
    │                               │
┌───▼────────────┐         ┌────────▼──────────┐
│  providers     │◄────────┤provider_documents │
│  (therapists   │         └───────────────────┘
│   & stores)    │
└───┬────────────┘
    │
    ├──────────────┬──────────────┬──────────────────┐
    │              │              │                  │
┌───▼───────┐ ┌───▼──────┐ ┌─────▼──────┐ ┌────────▼─────────┐
│therapist_ │ │  store_  │ │  provider_ │ │provider_services │
│ profiles  │ │ profiles │ │ locations  │ └──────────────────┘
└───────────┘ └──────────┘ └────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      SERVICE CATALOG                                 │
└─────────────────────────────────────────────────────────────────────┘
                    │
    ┌───────────────┼───────────────┐
    │                               │
┌───▼────────────┐         ┌────────▼──────────┐
│service_        │         │    services        │
│categories      │         │                    │
└────────────────┘         └────────┬───────────┘
                                    │
                           ┌────────▼──────────┐
                           │provider_services  │
                           └───────────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      BOOKING SYSTEM                                  │
└─────────────────────────────────────────────────────────────────────┘
                    │
              ┌─────▼─────┐
              │ bookings  │
              └─────┬─────┘
                    │
    ┌───────────────┼───────────────┬──────────────┐
    │               │               │              │
┌───▼────────┐ ┌────▼────────┐ ┌───▼───────┐ ┌───▼──────────┐
│booking_    │ │  booking_   │ │ booking_  │ │booking_status│
│locations   │ │  timeline   │ │ notes     │ │  _history    │
└────────────┘ └─────────────┘ └───────────┘ └──────────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                      PAYMENT SYSTEM                                  │
└─────────────────────────────────────────────────────────────────────┘
                    │
    ┌───────────────┼───────────────┬──────────────┐
    │               │               │              │
┌───▼────────┐ ┌────▼────────┐ ┌───▼───────┐ ┌───▼──────┐
│transactions│ │  payouts    │ │  wallets  │ │ promo_   │
│            │ │             │ │           │ │ codes    │
└────────────┘ └─────────────┘ └───────────┘ └──────────┘

┌─────────────────────────────────────────────────────────────────────┐
│                   REVIEWS & COMMUNICATIONS                           │
└─────────────────────────────────────────────────────────────────────┘
                    │
    ┌───────────────┼───────────────┐
    │                               │
┌───▼────────┐              ┌───────▼──────┐
│  reviews   │              │   messages   │
└────────────┘              └──────────────┘
```

---

## 3. Table Definitions

### 3.1 User Management

#### **users**
Primary table for all user accounts (customers, therapists, store owners, admins).

```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    phone VARCHAR(20) UNIQUE NOT NULL,
    phone_verified_at TIMESTAMP,
    email VARCHAR(255) UNIQUE,
    email_verified_at TIMESTAMP,
    password VARCHAR(255),

    
    -- Profile
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    gender ENUM('male', 'female', 'other', 'prefer_not_to_say'),
    date_of_birth DATE,
    profile_photo_url TEXT,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    last_active_at TIMESTAMP,
    
    -- Preferences
    preferred_language VARCHAR(5) DEFAULT 'en',
    notification_preferences JSONB DEFAULT '{}',
    
    -- Authentication
    remember_token VARCHAR(100),
    two_factor_secret TEXT,
    two_factor_recovery_codes TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    
    -- Indexes
    INDEX idx_users_phone (phone),
    INDEX idx_users_email (email),
    INDEX idx_users_uuid (uuid),
    INDEX idx_users_active (is_active, deleted_at)
);
```

#### **personal_access_tokens**
Laravel Sanctum API tokens.

```sql
CREATE TABLE personal_access_tokens (
    id BIGSERIAL PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    abilities TEXT,
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tokenable (tokenable_type, tokenable_id),
    INDEX idx_token (token)
);
```

#### **user_addresses**
Saved customer addresses (home, work, etc.).

```sql
CREATE TABLE user_addresses (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    label VARCHAR(50) DEFAULT 'home', -- home, work, other
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100),
    postal_code VARCHAR(10),
    country VARCHAR(2) DEFAULT 'PH',
    
    -- Geolocation
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    location GEOGRAPHY(POINT, 4326), -- PostGIS
    
    is_default BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_addresses_user (user_id),
    INDEX idx_user_addresses_location USING GIST(location)
);

-- Trigger to update PostGIS geography column
CREATE OR REPLACE FUNCTION update_user_address_location()
RETURNS TRIGGER AS $$
BEGIN
    NEW.location = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::geography;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_update_user_address_location
BEFORE INSERT OR UPDATE ON user_addresses
FOR EACH ROW
EXECUTE FUNCTION update_user_address_location();
```

#### **roles** & **permissions**
Using Spatie Laravel Permission package.

```sql
CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL, -- customer, therapist, store_owner, admin, super_admin
    guard_name VARCHAR(255) NOT NULL DEFAULT 'api',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    guard_name VARCHAR(255) NOT NULL DEFAULT 'api',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE model_has_roles (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type),
    INDEX idx_model_has_roles (model_id, model_type)
);

CREATE TABLE model_has_permissions (
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type)
);

CREATE TABLE role_has_permissions (
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    PRIMARY KEY (permission_id, role_id)
);
```

---

### 3.2 Provider Management

#### **providers**
Base table for all service providers (therapists and wellness centers).

```sql
CREATE TABLE providers (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type ENUM('therapist', 'store') NOT NULL,
    
    -- Verification
    verification_status ENUM('pending', 'under_review', 'verified', 'rejected') DEFAULT 'pending',
    verified_at TIMESTAMP,
    verified_by BIGINT REFERENCES users(id),
    rejection_reason TEXT,
    
    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    is_available BOOLEAN DEFAULT FALSE, -- for therapists (online/offline)
    accepts_home_service BOOLEAN DEFAULT TRUE,
    accepts_store_service BOOLEAN DEFAULT FALSE,
    
    -- Business details
    business_name VARCHAR(255),
    business_registration_number VARCHAR(100),
    tax_id VARCHAR(50),
    
    -- Rating & Stats
    average_rating DECIMAL(3, 2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    total_bookings INT DEFAULT 0,
    total_earnings DECIMAL(12, 2) DEFAULT 0.00,
    
    -- Commission
    commission_rate DECIMAL(5, 2) DEFAULT 15.00, -- percentage
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    
    INDEX idx_providers_user (user_id),
    INDEX idx_providers_type (type),
    INDEX idx_providers_status (verification_status, is_active),
    INDEX idx_providers_available (is_available, type)
);
```

#### **therapist_profiles**
Extended profile for individual therapists.

```sql
CREATE TABLE therapist_profiles (
    id BIGSERIAL PRIMARY KEY,
    provider_id BIGINT NOT NULL REFERENCES providers(id) ON DELETE CASCADE,
    
    -- Professional info
    license_number VARCHAR(100),
    license_type VARCHAR(100), -- PRC, TESDA, etc.
    license_expiry_date DATE,
    years_of_experience INT,
    specializations JSONB, -- ["Swedish", "Deep Tissue", "Sports", "Aromatherapy"]
    certifications JSONB, -- [{name: "", issuer: "", date: ""}]
    languages_spoken JSONB DEFAULT '["en", "fil"]',
    
    -- Bio
    bio TEXT,
    professional_photo_url TEXT,
    
    -- Service area (for home service)
    service_radius_km INT DEFAULT 5, -- max distance willing to travel
    base_location_latitude DECIMAL(10, 8),
    base_location_longitude DECIMAL(11, 8),
    base_location GEOGRAPHY(POINT, 4326), -- PostGIS
    
    -- Availability
    default_schedule JSONB, -- {monday: [{start: "09:00", end: "18:00"}], ...}
    
    -- Equipment
    has_own_equipment BOOLEAN DEFAULT TRUE,
    equipment_list JSONB,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(provider_id),
    INDEX idx_therapist_base_location USING GIST(base_location)
);

-- Trigger for base_location
CREATE OR REPLACE FUNCTION update_therapist_base_location()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.base_location_latitude IS NOT NULL AND NEW.base_location_longitude IS NOT NULL THEN
        NEW.base_location = ST_SetSRID(ST_MakePoint(NEW.base_location_longitude, NEW.base_location_latitude), 4326)::geography;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_update_therapist_base_location
BEFORE INSERT OR UPDATE ON therapist_profiles
FOR EACH ROW
EXECUTE FUNCTION update_therapist_base_location();
```

#### **store_profiles**
Extended profile for wellness centers/spas.

```sql
CREATE TABLE store_profiles (
    id BIGSERIAL PRIMARY KEY,
    provider_id BIGINT NOT NULL REFERENCES providers(id) ON DELETE CASCADE,
    
    -- Store details
    store_name VARCHAR(255) NOT NULL,
    description TEXT,
    logo_url TEXT,
    cover_photo_url TEXT,
    photos JSONB, -- array of image URLs
    
    -- Location
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100),
    postal_code VARCHAR(10),
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    location GEOGRAPHY(POINT, 4326), -- PostGIS
    
    -- Contact
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(255),
    
    -- Operating hours
    operating_hours JSONB, -- {monday: {open: "09:00", close: "21:00", closed: false}, ...}
    
    -- Facilities
    amenities JSONB, -- ["WiFi", "Parking", "Shower", "Sauna", "Lockers"]
    number_of_rooms INT DEFAULT 1,
    payment_methods JSONB, -- ["Cash", "Card", "GCash", "PayMaya"]
    
    -- Policies
    cancellation_policy TEXT,
    house_rules TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(provider_id),
    INDEX idx_store_location USING GIST(location)
);

-- Trigger for store location
CREATE OR REPLACE FUNCTION update_store_location()
RETURNS TRIGGER AS $$
BEGIN
    NEW.location = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::geography;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_update_store_location
BEFORE INSERT OR UPDATE ON store_profiles
FOR EACH ROW
EXECUTE FUNCTION update_store_location();
```

#### **provider_documents**
Uploaded verification documents.

```sql
CREATE TABLE provider_documents (
    id BIGSERIAL PRIMARY KEY,
    provider_id BIGINT NOT NULL REFERENCES providers(id) ON DELETE CASCADE,
    document_type VARCHAR(50) NOT NULL, -- government_id, license, nbi_clearance, health_cert, etc.
    file_url TEXT NOT NULL,
    file_name VARCHAR(255),
    file_size BIGINT, -- bytes
    mime_type VARCHAR(100),
    
    -- Verification
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verified_at TIMESTAMP,
    verified_by BIGINT REFERENCES users(id),
    rejection_reason TEXT,
    
    -- Expiry (for licenses, health certs)
    expiry_date DATE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_provider_documents_provider (provider_id),
    INDEX idx_provider_documents_type (document_type)
);
```

#### **provider_locations**
Real-time location tracking for therapists.

```sql
CREATE TABLE provider_locations (
    id BIGSERIAL PRIMARY KEY,
    provider_id BIGINT NOT NULL REFERENCES providers(id) ON DELETE CASCADE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    location GEOGRAPHY(POINT, 4326), -- PostGIS
    accuracy DECIMAL(6, 2), -- meters
    heading DECIMAL(5, 2), -- degrees
    speed DECIMAL(6, 2), -- km/h
    
    -- Status
    is_moving BOOLEAN DEFAULT FALSE,
    battery_level INT, -- percentage
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_provider_locations_provider (provider_id),
    INDEX idx_provider_locations_location USING GIST(location),
    INDEX idx_provider_locations_created (created_at)
);

-- Partition by month for performance
CREATE TABLE provider_locations_y2026m02 PARTITION OF provider_locations
FOR VALUES FROM ('2026-02-01') TO ('2026-03-01');

-- Trigger for location
CREATE OR REPLACE FUNCTION update_provider_location()
RETURNS TRIGGER AS $$
BEGIN
    NEW.location = ST_SetSRID(ST_MakePoint(NEW.longitude, NEW.latitude), 4326)::geography;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_update_provider_location
BEFORE INSERT ON provider_locations
FOR EACH ROW
EXECUTE FUNCTION update_provider_location();
```

---

### 3.3 Service Catalog

#### **service_categories**

```sql
CREATE TABLE service_categories (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon_url TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_service_categories_slug (slug)
);
```

#### **services**

```sql
CREATE TABLE services (
    id BIGSERIAL PRIMARY KEY,
    category_id BIGINT REFERENCES service_categories(id) ON DELETE SET NULL,
    
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    
    -- Pricing (base prices, can be overridden by providers)
    base_price DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'PHP',
    
    -- Duration
    duration_minutes INT NOT NULL, -- 30, 60, 90, 120
    
    -- Media
    image_url TEXT,
    
    -- Metadata
    benefits JSONB, -- ["Reduces stress", "Improves circulation"]
    contraindications JSONB, -- ["Pregnancy", "Recent surgery"]
    
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    
    INDEX idx_services_category (category_id),
    INDEX idx_services_slug (slug),
    INDEX idx_services_active (is_active)
);
```

#### **provider_services**
Services offered by each provider with custom pricing.

```sql
CREATE TABLE provider_services (
    id BIGSERIAL PRIMARY KEY,
    provider_id BIGINT NOT NULL REFERENCES providers(id) ON DELETE CASCADE,
    service_id BIGINT NOT NULL REFERENCES services(id) ON DELETE CASCADE,
    
    -- Custom pricing
    price DECIMAL(10, 2) NOT NULL,
    
    -- Availability
    is_available BOOLEAN DEFAULT TRUE,
    
    -- Service-specific settings
    home_service_enabled BOOLEAN DEFAULT TRUE,
    store_service_enabled BOOLEAN DEFAULT FALSE,
    
    -- Distance surcharge (for home service)
    base_distance_km INT DEFAULT 5,
    per_km_surcharge DECIMAL(8, 2) DEFAULT 0.00,
    max_travel_distance_km INT DEFAULT 10,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(provider_id, service_id),
    INDEX idx_provider_services_provider (provider_id),
    INDEX idx_provider_services_service (service_id)
);
```

---

### 3.4 Booking System

#### **bookings**
Core booking table.

```sql
CREATE TABLE bookings (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    booking_number VARCHAR(20) UNIQUE NOT NULL, -- SPC-2026-000001
    
    -- Parties
    customer_id BIGINT NOT NULL REFERENCES users(id),
    provider_id BIGINT NOT NULL REFERENCES providers(id),
    service_id BIGINT NOT NULL REFERENCES services(id),
    
    -- Booking type
    type ENUM('home_service', 'in_store') NOT NULL,
    
    -- Scheduling
    booking_type ENUM('now', 'scheduled') NOT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scheduled_at TIMESTAMP, -- for scheduled bookings
    accepted_at TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    cancelled_at TIMESTAMP,
    
    -- Status
    status ENUM(
        'pending',        -- waiting for provider acceptance
        'accepted',       -- provider accepted
        'en_route',       -- therapist on the way (home service)
        'arrived',        -- therapist arrived
        'in_progress',    -- service ongoing
        'completed',      -- service done
        'cancelled',      -- cancelled by customer/provider
        'no_show'         -- customer didn't show up (in-store)
    ) DEFAULT 'pending',
    
    -- Pricing
    service_price DECIMAL(10, 2) NOT NULL,
    distance_surcharge DECIMAL(8, 2) DEFAULT 0.00,
    platform_fee DECIMAL(8, 2) DEFAULT 0.00,
    tip_amount DECIMAL(8, 2) DEFAULT 0.00,
    promo_discount DECIMAL(8, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,
    
    -- Provider earnings
    provider_earnings DECIMAL(10, 2) NOT NULL,
    commission_amount DECIMAL(8, 2) NOT NULL,
    commission_rate DECIMAL(5, 2) NOT NULL,
    
    -- Special instructions
    customer_notes TEXT,
    provider_notes TEXT,
    
    -- Cancellation
    cancelled_by BIGINT REFERENCES users(id),
    cancellation_reason TEXT,
    cancellation_fee DECIMAL(8, 2) DEFAULT 0.00,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    
    INDEX idx_bookings_customer (customer_id),
    INDEX idx_bookings_provider (provider_id),
    INDEX idx_bookings_status (status),
    INDEX idx_bookings_type (type),
    INDEX idx_bookings_scheduled (scheduled_at),
    INDEX idx_bookings_created (created_at)
);

-- Auto-increment booking number
CREATE SEQUENCE booking_number_seq START 1;

CREATE OR REPLACE FUNCTION generate_booking_number()
RETURNS TRIGGER AS $$
BEGIN
    NEW.booking_number = 'SPC-' || TO_CHAR(CURRENT_DATE, 'YYYY') || '-' || 
                         LPAD(nextval('booking_number_seq')::TEXT, 6, '0');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_generate_booking_number
BEFORE INSERT ON bookings
FOR EACH ROW
EXECUTE FUNCTION generate_booking_number();
```

#### **booking_locations**
Pickup/destination for home service bookings.

```sql
CREATE TABLE booking_locations (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    
    -- Customer location
    address VARCHAR(500) NOT NULL,
    unit_number VARCHAR(50),
    building_name VARCHAR(255),
    landmark VARCHAR(255),
    
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    location GEOGRAPHY(POINT, 4326), -- PostGIS
    
    -- Distance calculation (from provider to customer)
    distance_km DECIMAL(6, 2),
    estimated_travel_time_minutes INT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(booking_id),
    INDEX idx_booking_locations_location USING GIST(location)
);
```

#### **booking_timeline**
Track booking status changes.

```sql
CREATE TABLE booking_timeline (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    
    status VARCHAR(50) NOT NULL,
    description TEXT,
    metadata JSONB, -- additional data (e.g., location, ETA)
    
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_booking_timeline_booking (booking_id),
    INDEX idx_booking_timeline_status (status)
);

-- Trigger to auto-log status changes
CREATE OR REPLACE FUNCTION log_booking_status_change()
RETURNS TRIGGER AS $$
BEGIN
    IF (TG_OP = 'UPDATE' AND OLD.status IS DISTINCT FROM NEW.status) THEN
        INSERT INTO booking_timeline (booking_id, status, description)
        VALUES (NEW.id, NEW.status, 'Status changed to ' || NEW.status);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_log_booking_status_change
AFTER UPDATE ON bookings
FOR EACH ROW
EXECUTE FUNCTION log_booking_status_change();
```

---

### 3.5 Payment System

#### **transactions**

```sql
CREATE TABLE transactions (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    transaction_number VARCHAR(20) UNIQUE NOT NULL,
    
    booking_id BIGINT REFERENCES bookings(id) ON DELETE SET NULL,
    user_id BIGINT NOT NULL REFERENCES users(id),
    
    -- Transaction details
    type ENUM('payment', 'refund', 'payout', 'wallet_topup', 'tip') NOT NULL,
    payment_method ENUM('card', 'gcash', 'paymaya', 'wallet', 'cash') NOT NULL,
    
    -- Amounts
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'PHP',
    
    -- Gateway details
    gateway VARCHAR(50), -- stripe, paymongo, etc.
    gateway_transaction_id VARCHAR(255),
    gateway_response JSONB,
    
    -- Status
    status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    
    -- Metadata
    description TEXT,
    metadata JSONB,
    
    processed_at TIMESTAMP,
    failed_at TIMESTAMP,
    failure_reason TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_transactions_booking (booking_id),
    INDEX idx_transactions_user (user_id),
    INDEX idx_transactions_status (status),
    INDEX idx_transactions_gateway_id (gateway_transaction_id)
);
```

#### **wallets**

```sql
CREATE TABLE wallets (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    balance DECIMAL(12, 2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'PHP',
    
    total_earned DECIMAL(12, 2) DEFAULT 0.00, -- for providers
    total_spent DECIMAL(12, 2) DEFAULT 0.00, -- for customers
    
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(user_id),
    INDEX idx_wallets_user (user_id)
);

-- Trigger to create wallet on user creation
CREATE OR REPLACE FUNCTION create_user_wallet()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO wallets (user_id) VALUES (NEW.id);
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_create_user_wallet
AFTER INSERT ON users
FOR EACH ROW
EXECUTE FUNCTION create_user_wallet();
```

#### **wallet_transactions**

```sql
CREATE TABLE wallet_transactions (
    id BIGSERIAL PRIMARY KEY,
    wallet_id BIGINT NOT NULL REFERENCES wallets(id) ON DELETE CASCADE,
    
    type ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    
    balance_before DECIMAL(12, 2) NOT NULL,
    balance_after DECIMAL(12, 2) NOT NULL,
    
    reference_type VARCHAR(255), -- Booking, Transaction, etc.
    reference_id BIGINT,
    
    description TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_wallet_transactions_wallet (wallet_id),
    INDEX idx_wallet_transactions_created (created_at)
);
```

#### **payouts**
Provider earnings payouts.

```sql
CREATE TABLE payouts (
    id BIGSERIAL PRIMARY KEY,
    provider_id BIGINT NOT NULL REFERENCES providers(id) ON DELETE CASCADE,
    
    -- Payout period
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    
    -- Amounts
    gross_earnings DECIMAL(12, 2) NOT NULL,
    commission_amount DECIMAL(12, 2) NOT NULL,
    net_payout DECIMAL(12, 2) NOT NULL,
    
    -- Bank details
    bank_name VARCHAR(100),
    account_name VARCHAR(255),
    account_number VARCHAR(50),
    
    -- Status
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    
    processed_at TIMESTAMP,
    failed_at TIMESTAMP,
    failure_reason TEXT,
    
    -- Reference
    reference_number VARCHAR(50),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_payouts_provider (provider_id),
    INDEX idx_payouts_status (status),
    INDEX idx_payouts_period (period_start, period_end)
);
```

#### **promo_codes**

```sql
CREATE TABLE promo_codes (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    
    -- Discount
    discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
    discount_value DECIMAL(10, 2) NOT NULL,
    max_discount_amount DECIMAL(10, 2), -- for percentage discounts
    
    -- Validity
    valid_from TIMESTAMP NOT NULL,
    valid_until TIMESTAMP NOT NULL,
    
    -- Limits
    usage_limit INT, -- total uses allowed
    usage_count INT DEFAULT 0,
    per_user_limit INT DEFAULT 1,
    min_booking_amount DECIMAL(10, 2),
    
    -- Applicability
    applicable_service_ids JSONB, -- null = all services
    applicable_provider_ids JSONB,
    new_customers_only BOOLEAN DEFAULT FALSE,
    
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_promo_codes_code (code),
    INDEX idx_promo_codes_validity (valid_from, valid_until)
);
```

#### **promo_code_usages**

```sql
CREATE TABLE promo_code_usages (
    id BIGSERIAL PRIMARY KEY,
    promo_code_id BIGINT NOT NULL REFERENCES promo_codes(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    booking_id BIGINT REFERENCES bookings(id) ON DELETE SET NULL,
    
    discount_amount DECIMAL(10, 2) NOT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_promo_usages_code (promo_code_id),
    INDEX idx_promo_usages_user (user_id)
);
```

---

### 3.6 Reviews & Communications

#### **reviews**

```sql
CREATE TABLE reviews (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    
    reviewer_id BIGINT NOT NULL REFERENCES users(id), -- customer or provider
    reviewee_id BIGINT NOT NULL REFERENCES users(id), -- provider or customer
    reviewer_type ENUM('customer', 'provider') NOT NULL,
    
    -- Rating (1-5 stars)
    overall_rating INT NOT NULL CHECK (overall_rating >= 1 AND overall_rating <= 5),
    
    -- Detailed ratings (optional)
    professionalism_rating INT CHECK (professionalism_rating >= 1 AND professionalism_rating <= 5),
    communication_rating INT CHECK (communication_rating >= 1 AND communication_rating <= 5),
    timeliness_rating INT CHECK (timeliness_rating >= 1 AND timeliness_rating <= 5),
    quality_rating INT CHECK (quality_rating >= 1 AND quality_rating <= 5),
    
    -- Review content
    comment TEXT,
    tags JSONB, -- ["Professional", "On-time", "Great technique"]
    
    -- Moderation
    is_visible BOOLEAN DEFAULT TRUE,
    is_flagged BOOLEAN DEFAULT FALSE,
    flagged_reason TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    
    UNIQUE(booking_id, reviewer_id),
    INDEX idx_reviews_booking (booking_id),
    INDEX idx_reviews_reviewee (reviewee_id),
    INDEX idx_reviews_rating (overall_rating)
);

-- Trigger to update provider rating
CREATE OR REPLACE FUNCTION update_provider_rating()
RETURNS TRIGGER AS $$
DECLARE
    provider_user_id BIGINT;
    avg_rating DECIMAL(3, 2);
    review_count INT;
BEGIN
    -- Get provider user_id from booking
    SELECT provider_id INTO provider_user_id
    FROM bookings b
    JOIN providers p ON b.provider_id = p.id
    WHERE b.id = NEW.booking_id;
    
    -- Calculate new average
    SELECT AVG(overall_rating), COUNT(*)
    INTO avg_rating, review_count
    FROM reviews
    WHERE reviewee_id = provider_user_id AND reviewer_type = 'customer';
    
    -- Update provider
    UPDATE providers
    SET average_rating = avg_rating,
        total_reviews = review_count
    WHERE user_id = provider_user_id;
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_update_provider_rating
AFTER INSERT OR UPDATE ON reviews
FOR EACH ROW
WHEN (NEW.reviewer_type = 'customer')
EXECUTE FUNCTION update_provider_rating();
```

#### **messages**

```sql
CREATE TABLE messages (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    
    booking_id BIGINT NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    sender_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    message TEXT NOT NULL,
    
    -- Attachments
    attachment_type VARCHAR(50), -- image, location
    attachment_url TEXT,
    attachment_metadata JSONB,
    
    -- Status
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP,
    
    INDEX idx_messages_booking (booking_id),
    INDEX idx_messages_sender (sender_id),
    INDEX idx_messages_receiver (receiver_id),
    INDEX idx_messages_created (created_at)
);
```

---

### 3.7 Notifications

#### **notifications**

```sql
CREATE TABLE notifications (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE NOT NULL DEFAULT gen_random_uuid(),
    
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    type VARCHAR(100) NOT NULL, -- booking_confirmed, therapist_en_route, etc.
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    
    -- Action
    action_type VARCHAR(50), -- open_booking, open_chat, etc.
    action_data JSONB,
    
    -- Channels
    sent_via JSONB DEFAULT '[]', -- ["push", "sms", "email"]
    
    -- Status
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_notifications_user (user_id),
    INDEX idx_notifications_read (is_read),
    INDEX idx_notifications_created (created_at)
);
```

#### **device_tokens**
For push notifications.

```sql
CREATE TABLE device_tokens (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    device_type ENUM('android', 'ios', 'web') NOT NULL,
    token TEXT NOT NULL,
    
    device_name VARCHAR(255),
    device_os_version VARCHAR(50),
    app_version VARCHAR(50),
    
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(user_id, token),
    INDEX idx_device_tokens_user (user_id)
);
```

---

### 3.8 System Tables

#### **app_settings**

```sql
CREATE TABLE app_settings (
    id BIGSERIAL PRIMARY KEY,
    key VARCHAR(100) UNIQUE NOT NULL,
    value JSONB NOT NULL,
    description TEXT,
    
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_app_settings_key (key)
);

-- Seed common settings
INSERT INTO app_settings (key, value, description) VALUES
('commission_rate', '{"default": 15, "premium": 10}', 'Platform commission rates'),
('booking_timeout', '{"pending": 300, "accepted": 1800}', 'Booking timeout in seconds'),
('cancellation_policy', '{"free_before_hours": 24, "fee_percentage": 50}', 'Cancellation policy'),
('service_radius_max', '20', 'Maximum service radius in km');
```

#### **audit_logs**

```sql
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    
    action VARCHAR(100) NOT NULL,
    auditable_type VARCHAR(255) NOT NULL,
    auditable_id BIGINT NOT NULL,
    
    old_values JSONB,
    new_values JSONB,
    
    ip_address INET,
    user_agent TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_audit_logs_user (user_id),
    INDEX idx_audit_logs_auditable (auditable_type, auditable_id),
    INDEX idx_audit_logs_created (created_at)
);
```

---

## 4. Indexes Summary

### 4.1 B-Tree Indexes
Standard indexes for exact matches and range queries.

```sql
-- Critical performance indexes
CREATE INDEX CONCURRENTLY idx_bookings_customer_status ON bookings(customer_id, status);
CREATE INDEX CONCURRENTLY idx_bookings_provider_status ON bookings(provider_id, status);
CREATE INDEX CONCURRENTLY idx_providers_available ON providers(is_available) WHERE is_available = TRUE AND deleted_at IS NULL;
```

### 4.2 GiST Indexes
For geospatial queries (PostGIS).

```sql
-- All location-based indexes use GIST
CREATE INDEX CONCURRENTLY idx_user_addresses_location ON user_addresses USING GIST(location);
CREATE INDEX CONCURRENTLY idx_therapist_profiles_base_location ON therapist_profiles USING GIST(base_location);
CREATE INDEX CONCURRENTLY idx_store_profiles_location ON store_profiles USING GIST(location);
CREATE INDEX CONCURRENTLY idx_provider_locations_location ON provider_locations USING GIST(location);
CREATE INDEX CONCURRENTLY idx_booking_locations_location ON booking_locations USING GIST(location);
```

### 4.3 Partial Indexes
For common filtered queries.

```sql
-- Active providers only
CREATE INDEX idx_providers_active ON providers(id) WHERE is_active = TRUE AND deleted_at IS NULL;

-- Available therapists for matching
CREATE INDEX idx_providers_available_therapists ON providers(id, user_id) 
WHERE type = 'therapist' AND is_available = TRUE AND verification_status = 'verified';

-- Pending bookings
CREATE INDEX idx_bookings_pending ON bookings(created_at) WHERE status = 'pending';
```

---

## 5. Data Relationships

### 5.1 One-to-One Relationships
- `users` → `wallets`
- `providers` → `therapist_profiles` (if type = 'therapist')
- `providers` → `store_profiles` (if type = 'store')
- `bookings` → `booking_locations` (if type = 'home_service')

### 5.2 One-to-Many Relationships
- `users` → `user_addresses`
- `users` → `bookings` (as customer)
- `providers` → `bookings`
- `providers` → `provider_documents`
- `providers` → `provider_services`
- `providers` → `provider_locations`
- `bookings` → `messages`
- `bookings` → `booking_timeline`

### 5.3 Many-to-Many Relationships
- `providers` ↔ `services` (through `provider_services`)
- `users` ↔ `roles` (through `model_has_roles`)
- `users` ↔ `permissions` (through `model_has_permissions`)

---

## 6. Sample Queries

### 6.1 Find Nearby Available Therapists

```sql
-- Find therapists within 5km of customer location
SELECT 
    p.id,
    u.first_name,
    u.last_name,
    tp.specializations,
    p.average_rating,
    ST_Distance(
        tp.base_location,
        ST_SetSRID(ST_MakePoint(121.0244, 14.5547), 4326)::geography
    ) / 1000 AS distance_km
FROM providers p
JOIN users u ON p.user_id = u.id
JOIN therapist_profiles tp ON p.id = tp.provider_id
WHERE p.type = 'therapist'
  AND p.is_available = TRUE
  AND p.verification_status = 'verified'
  AND ST_DWithin(
      tp.base_location,
      ST_SetSRID(ST_MakePoint(121.0244, 14.5547), 4326)::geography,
      5000  -- 5km in meters
  )
ORDER BY distance_km ASC
LIMIT 10;
```

### 6.2 Calculate Provider Earnings for Payout

```sql
-- Calculate earnings for a provider in a date range
SELECT 
    p.id AS provider_id,
    u.first_name || ' ' || u.last_name AS provider_name,
    COUNT(b.id) AS total_bookings,
    SUM(b.service_price + b.distance_surcharge + b.tip_amount) AS gross_earnings,
    SUM(b.commission_amount) AS total_commission,
    SUM(b.provider_earnings + b.tip_amount) AS net_earnings
FROM providers p
JOIN users u ON p.user_id = u.id
JOIN bookings b ON p.id = b.provider_id
WHERE b.status = 'completed'
  AND b.completed_at BETWEEN '2026-01-27' AND '2026-02-03'
  AND p.id = 123
GROUP BY p.id, u.first_name, u.last_name;
```

### 6.3 Get Booking History with Details

```sql
-- Customer booking history
SELECT 
    b.booking_number,
    b.type,
    b.status,
    s.name AS service_name,
    u.first_name AS therapist_first_name,
    b.scheduled_at,
    b.total_amount,
    r.overall_rating
FROM bookings b
JOIN services s ON b.service_id = s.id
JOIN providers p ON b.provider_id = p.id
JOIN users u ON p.user_id = u.id
LEFT JOIN reviews r ON b.id = r.booking_id AND r.reviewer_type = 'customer'
WHERE b.customer_id = 456
ORDER BY b.created_at DESC
LIMIT 20;
```

---

## 7. Seed Data

### 7.1 Initial Roles

```sql
INSERT INTO roles (name, guard_name) VALUES
('customer', 'api'),
('therapist', 'api'),
('store_owner', 'api'),
('admin', 'api'),
('super_admin', 'api');
```

### 7.2 Service Categories

```sql
INSERT INTO service_categories (name, slug, description, sort_order) VALUES
('Massage Therapy', 'massage-therapy', 'Professional therapeutic massage services', 1),
('Spa Treatments', 'spa-treatments', 'Relaxation and beauty treatments', 2),
('Physical Therapy', 'physical-therapy', 'Rehabilitation and recovery services', 3);
```

### 7.3 Services

```sql
INSERT INTO services (category_id, name, slug, base_price, duration_minutes, description) VALUES
(1, 'Swedish Massage', 'swedish-massage', 800.00, 60, 'Gentle, relaxing full-body massage'),
(1, 'Deep Tissue Massage', 'deep-tissue-massage', 1000.00, 60, 'Intense pressure for muscle tension relief'),
(1, 'Sports Massage', 'sports-massage', 1200.00, 60, 'For athletes and active individuals'),
(1, 'Aromatherapy Massage', 'aromatherapy-massage', 1100.00, 90, 'Massage with essential oils'),
(1, 'Hot Stone Massage', 'hot-stone-massage', 1500.00, 90, 'Therapeutic massage with heated stones'),
(2, 'Foot Spa', 'foot-spa', 500.00, 30, 'Relaxing foot massage and treatment'),
(2, 'Body Scrub', 'body-scrub', 800.00, 45, 'Exfoliation treatment');
```

---

## 8. Database Maintenance

### 8.1 Vacuum & Analyze

```sql
-- Regular maintenance (automated via cron)
VACUUM ANALYZE bookings;
VACUUM ANALYZE provider_locations;
VACUUM ANALYZE messages;
```

### 8.2 Partition Management

```sql
-- Create new partition for provider_locations (monthly)
CREATE TABLE provider_locations_y2026m03 PARTITION OF provider_locations
FOR VALUES FROM ('2026-03-01') TO ('2026-04-01');

-- Drop old partitions (after 3 months)
DROP TABLE provider_locations_y2025m12;
```

### 8.3 Index Maintenance

```sql
-- Rebuild indexes periodically
REINDEX TABLE CONCURRENTLY bookings;
REINDEX TABLE CONCURRENTLY provider_locations;
```

---

## 9. Backup & Recovery

### 9.1 Backup Strategy

```bash
# Full database backup (daily at 2 AM)
pg_dump -h localhost -U spacall -F c -b -v -f /backups/spacall_$(date +%Y%m%d).backup spacall_production

# Backup retention: 7 daily, 4 weekly, 12 monthly
```

### 9.2 Point-in-Time Recovery

```sql
-- Enable WAL archiving in postgresql.conf
wal_level = replica
archive_mode = on
archive_command = 'cp %p /var/lib/postgresql/wal_archive/%f'
```

---

**Document Control**
- Next Review: 2026-03-03
- Change Log: See CHANGELOG.md