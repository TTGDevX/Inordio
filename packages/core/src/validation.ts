/**
 * Zod validation schemas for Inordio
 */

import { z } from 'zod';

// ===========================================
// COMMON SCHEMAS
// ===========================================

export const emailSchema = z.string().email('Invalid email address');

export const passwordSchema = z
  .string()
  .min(8, 'Password must be at least 8 characters')
  .regex(/[A-Z]/, 'Password must contain at least one uppercase letter')
  .regex(/[a-z]/, 'Password must contain at least one lowercase letter')
  .regex(/[0-9]/, 'Password must contain at least one number');

export const phoneSchema = z
  .string()
  .regex(/^[\d\s\-\(\)\+]+$/, 'Invalid phone number')
  .optional();

export const postalCodeSchema = z
  .string()
  .regex(/^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/, 'Invalid Canadian postal code');

// ===========================================
// TENANT SCHEMAS
// ===========================================

export const createTenantSchema = z.object({
  name: z.string().min(2, 'Company name must be at least 2 characters'),
  slug: z
    .string()
    .min(3, 'Slug must be at least 3 characters')
    .max(50, 'Slug must be at most 50 characters')
    .regex(/^[a-z0-9-]+$/, 'Slug can only contain lowercase letters, numbers, and hyphens'),
  email: emailSchema,
  phone: phoneSchema,
});

export type CreateTenantInput = z.infer<typeof createTenantSchema>;

// ===========================================
// USER SCHEMAS
// ===========================================

export const createUserSchema = z.object({
  email: emailSchema,
  password: passwordSchema,
  name: z.string().min(2, 'Name must be at least 2 characters'),
  phone: phoneSchema,
  role: z.enum(['OWNER', 'ADMIN', 'OFFICE', 'TECHNICIAN', 'VIEWER']),
});

export type CreateUserInput = z.infer<typeof createUserSchema>;

export const loginSchema = z.object({
  email: emailSchema,
  password: z.string().min(1, 'Password is required'),
  tenantSlug: z.string().optional(),
});

export type LoginInput = z.infer<typeof loginSchema>;

// ===========================================
// LOCATION SCHEMAS
// ===========================================

export const createLocationSchema = z.object({
  name: z.string().min(2, 'Location name must be at least 2 characters'),
  type: z.enum(['WAREHOUSE', 'TRUCK', 'JOBSITE']),
  address: z.string().optional(),
  assignedUserId: z.string().uuid().optional(),
  vehicleInfo: z
    .object({
      make: z.string().optional(),
      model: z.string().optional(),
      year: z.number().optional(),
      plate: z.string().optional(),
    })
    .optional(),
});

export type CreateLocationInput = z.infer<typeof createLocationSchema>;

// ===========================================
// CANADIAN TAX SCHEMAS
// ===========================================

export const provinceTaxRates = {
  ON: { type: 'HST', rate: 0.13 },
  AB: { type: 'GST', rate: 0.05 },
  BC: { type: 'GST+PST', gst: 0.05, pst: 0.07 },
  SK: { type: 'GST+PST', gst: 0.05, pst: 0.06 },
  MB: { type: 'GST+PST', gst: 0.05, pst: 0.07 },
  QC: { type: 'GST+QST', gst: 0.05, qst: 0.09975 },
  NB: { type: 'HST', rate: 0.15 },
  NS: { type: 'HST', rate: 0.15 },
  PE: { type: 'HST', rate: 0.15 },
  NL: { type: 'HST', rate: 0.15 },
  YT: { type: 'GST', rate: 0.05 },
  NT: { type: 'GST', rate: 0.05 },
  NU: { type: 'GST', rate: 0.05 },
} as const;

export type Province = keyof typeof provinceTaxRates;

export const provinceSchema = z.enum([
  'ON', 'AB', 'BC', 'SK', 'MB', 'QC', 'NB', 'NS', 'PE', 'NL', 'YT', 'NT', 'NU'
]);
