/**
 * Inordio Encryption Module
 * 
 * Client-side end-to-end encryption using Web Crypto API.
 * This module provides zero-knowledge encryption where the server
 * never has access to encryption keys or unencrypted data.
 * 
 * IMPORTANT: This code runs in the BROWSER, not on the server.
 * All encryption/decryption happens client-side.
 */

// Type definitions
export interface EncryptedData {
  iv: string;      // Base64 encoded initialization vector
  data: string;    // Base64 encoded ciphertext
  version: number; // Encryption version for future migrations
}

export interface WrappedKey {
  wrappedKey: string;  // Base64 encoded wrapped key
  salt: string;        // Base64 encoded salt for key derivation
  version: number;
}

// Constants
const ENCRYPTION_VERSION = 1;
const PBKDF2_ITERATIONS = 210000;
const AES_KEY_LENGTH = 256;

/**
 * Convert ArrayBuffer to Base64 string
 */
export function bufferToBase64(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary);
}

/**
 * Convert Base64 string to ArrayBuffer
 */
export function base64ToBuffer(base64: string): ArrayBuffer {
  const binary = atob(base64);
  const bytes = new Uint8Array(binary.length);
  for (let i = 0; i < binary.length; i++) {
    bytes[i] = binary.charCodeAt(i);
  }
  return bytes.buffer;
}

/**
 * Derive an encryption key from a password using PBKDF2
 */
export async function deriveKeyFromPassword(
  password: string,
  salt: Uint8Array
): Promise<CryptoKey> {
  // Import password as key material
  const keyMaterial = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(password),
    'PBKDF2',
    false,
    ['deriveBits', 'deriveKey']
  );

  // Derive AES-GCM key
  return crypto.subtle.deriveKey(
    {
      name: 'PBKDF2',
      salt,
      iterations: PBKDF2_ITERATIONS,
      hash: 'SHA-256',
    },
    keyMaterial,
    { name: 'AES-GCM', length: AES_KEY_LENGTH },
    true, // extractable - needed for key wrapping
    ['encrypt', 'decrypt', 'wrapKey', 'unwrapKey']
  );
}

/**
 * Generate a new random encryption key
 */
export async function generateEncryptionKey(): Promise<CryptoKey> {
  return crypto.subtle.generateKey(
    { name: 'AES-GCM', length: AES_KEY_LENGTH },
    true, // extractable - needed for key wrapping
    ['encrypt', 'decrypt']
  );
}

/**
 * Generate random salt
 */
export function generateSalt(): Uint8Array {
  return crypto.getRandomValues(new Uint8Array(32));
}

/**
 * Wrap (encrypt) a key with another key for storage
 * Used to store the user's encryption key, encrypted with their password-derived key
 */
export async function wrapKey(
  keyToWrap: CryptoKey,
  wrappingKey: CryptoKey
): Promise<WrappedKey> {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  
  const wrappedKeyBuffer = await crypto.subtle.wrapKey(
    'raw',
    keyToWrap,
    wrappingKey,
    { name: 'AES-GCM', iv }
  );

  return {
    wrappedKey: bufferToBase64(iv) + '.' + bufferToBase64(wrappedKeyBuffer),
    salt: '', // Salt is stored separately
    version: ENCRYPTION_VERSION,
  };
}

/**
 * Unwrap (decrypt) a key
 */
export async function unwrapKey(
  wrappedKey: string,
  unwrappingKey: CryptoKey
): Promise<CryptoKey> {
  const [ivBase64, keyBase64] = wrappedKey.split('.');
  const iv = new Uint8Array(base64ToBuffer(ivBase64));
  const keyBuffer = base64ToBuffer(keyBase64);

  return crypto.subtle.unwrapKey(
    'raw',
    keyBuffer,
    unwrappingKey,
    { name: 'AES-GCM', iv },
    { name: 'AES-GCM', length: AES_KEY_LENGTH },
    true,
    ['encrypt', 'decrypt']
  );
}

/**
 * Encrypt a string value
 */
export async function encrypt(
  plaintext: string,
  key: CryptoKey
): Promise<EncryptedData> {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const encodedText = new TextEncoder().encode(plaintext);

  const ciphertext = await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv },
    key,
    encodedText
  );

  return {
    iv: bufferToBase64(iv),
    data: bufferToBase64(ciphertext),
    version: ENCRYPTION_VERSION,
  };
}

/**
 * Decrypt an encrypted value
 */
export async function decrypt(
  encrypted: EncryptedData,
  key: CryptoKey
): Promise<string> {
  const iv = new Uint8Array(base64ToBuffer(encrypted.iv));
  const ciphertext = base64ToBuffer(encrypted.data);

  const plaintext = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv },
    key,
    ciphertext
  );

  return new TextDecoder().decode(plaintext);
}

/**
 * Encrypt an object (serializes to JSON first)
 */
export async function encryptObject<T>(
  obj: T,
  key: CryptoKey
): Promise<EncryptedData> {
  return encrypt(JSON.stringify(obj), key);
}

/**
 * Decrypt to an object
 */
export async function decryptObject<T>(
  encrypted: EncryptedData,
  key: CryptoKey
): Promise<T> {
  const json = await decrypt(encrypted, key);
  return JSON.parse(json) as T;
}

/**
 * Serialize encrypted data for storage (as single string)
 */
export function serializeEncrypted(encrypted: EncryptedData): string {
  return `${encrypted.version}.${encrypted.iv}.${encrypted.data}`;
}

/**
 * Deserialize encrypted data from storage
 */
export function deserializeEncrypted(serialized: string): EncryptedData {
  const [version, iv, data] = serialized.split('.');
  return {
    version: parseInt(version, 10),
    iv,
    data,
  };
}

/**
 * Encryption context class for managing a user session
 */
export class EncryptionContext {
  private encryptionKey: CryptoKey | null = null;

  /**
   * Initialize encryption context with user's password
   * Called during login
   */
  async initialize(
    password: string,
    wrappedKey: string,
    saltBase64: string
  ): Promise<void> {
    const salt = new Uint8Array(base64ToBuffer(saltBase64));
    const passwordKey = await deriveKeyFromPassword(password, salt);
    this.encryptionKey = await unwrapKey(wrappedKey, passwordKey);
  }

  /**
   * Create new encryption context for new user
   * Returns the wrapped key and salt to store
   */
  async createNew(password: string): Promise<{ wrappedKey: string; salt: string }> {
    const salt = generateSalt();
    const passwordKey = await deriveKeyFromPassword(password, salt);
    this.encryptionKey = await generateEncryptionKey();
    const wrapped = await wrapKey(this.encryptionKey, passwordKey);
    
    return {
      wrappedKey: wrapped.wrappedKey,
      salt: bufferToBase64(salt),
    };
  }

  /**
   * Encrypt a value
   */
  async encrypt(plaintext: string): Promise<string> {
    if (!this.encryptionKey) {
      throw new Error('Encryption context not initialized');
    }
    const encrypted = await encrypt(plaintext, this.encryptionKey);
    return serializeEncrypted(encrypted);
  }

  /**
   * Decrypt a value
   */
  async decrypt(serialized: string): Promise<string> {
    if (!this.encryptionKey) {
      throw new Error('Encryption context not initialized');
    }
    const encrypted = deserializeEncrypted(serialized);
    return decrypt(encrypted, this.encryptionKey);
  }

  /**
   * Clear the encryption key from memory
   * Called during logout
   */
  clear(): void {
    this.encryptionKey = null;
  }

  /**
   * Check if context is initialized
   */
  isInitialized(): boolean {
    return this.encryptionKey !== null;
  }
}

// Export singleton for use in React app
export const encryptionContext = new EncryptionContext();
