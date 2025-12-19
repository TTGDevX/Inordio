// Re-export Prisma Client
export * from '@prisma/client';

// Export singleton instance
import { PrismaClient } from '@prisma/client';

const globalForPrisma = globalThis as unknown as {
  prisma: PrismaClient | undefined;
};

export const prisma =
  globalForPrisma.prisma ??
  new PrismaClient({
    log: process.env.NODE_ENV === 'development' ? ['query', 'error', 'warn'] : ['error'],
  });

if (process.env.NODE_ENV !== 'production') globalForPrisma.prisma = prisma;

// Tenant context helper
export async function withTenant<T>(
  tenantId: string,
  callback: (client: PrismaClient) => Promise<T>
): Promise<T> {
  // Set the tenant context for RLS
  await prisma.$executeRawUnsafe(`SET app.current_tenant_id = '${tenantId}'`);
  
  try {
    return await callback(prisma);
  } finally {
    // Reset the tenant context
    await prisma.$executeRawUnsafe(`RESET app.current_tenant_id`);
  }
}
