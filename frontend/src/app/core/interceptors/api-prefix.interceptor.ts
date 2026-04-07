import { inject } from '@angular/core';
import { HttpInterceptorFn } from '@angular/common/http';
import { DOCUMENT } from '@angular/common';

export const apiPrefixInterceptor: HttpInterceptorFn = (req, next) => {
  const document = inject(DOCUMENT);
  const baseHref = document.querySelector('base')?.getAttribute('href') ?? '/';
  if (req.url.startsWith('/api/')) {
    const base = baseHref.endsWith('/') ? baseHref.slice(0, -1) : baseHref;
    req = req.clone({ url: base + req.url });
  }
  return next(req);
};
