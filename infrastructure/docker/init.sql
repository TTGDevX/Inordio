-- ===========================================
-- INORDIO DATABASE INITIALIZATION
-- ===========================================
-- This script runs when PostgreSQL container first starts

-- Create extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Create application role (used by the app)
CREATE ROLE inordio_app WITH LOGIN PASSWORD 'inordio_app_password';

-- Grant permissions
GRANT ALL PRIVILEGES ON DATABASE inordio_dev TO inordio_app;

-- Note: RLS policies will be created by Prisma migrations
-- See packages/database/prisma/migrations for RLS setup

\echo 'Inordio database initialized successfully'
