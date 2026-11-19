import { z } from 'zod';

export const emailAddress = z.email({ pattern: z.regexes.html5Email, error: 'Masukkan alamat email yang valid.' }).max(255);
export const month = z.string().regex(/^\d{4}-(0[1-9]|1[0-2])$/, 'Pilih bulan yang valid.');
export const optionalId = z.union([z.literal(''), z.ulid()]);
export const readerSettings = z.object({ restricted: z.boolean(), readers: z.array(z.ulid()).max(300) });
