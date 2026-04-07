import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface CalendarEvent {
  id: number;
  title: string;
  start: string[];
  tags: string[];
  description: string;
}

export type CalendarEventPayload = Omit<CalendarEvent, 'id'>;

@Injectable({ providedIn: 'root' })
export class EventService {
  private readonly http = inject(HttpClient);
  private readonly base = '/api/events';

  getEvents(): Observable<CalendarEvent[]> {
    return this.http.get<CalendarEvent[]>(this.base);
  }

  getEvent(id: number): Observable<CalendarEvent> {
    return this.http.get<CalendarEvent>(`${this.base}/${id}`);
  }

  createEvent(event: CalendarEventPayload): Observable<CalendarEvent> {
    return this.http.post<CalendarEvent>(this.base, event);
  }

  updateEvent(id: number, event: CalendarEventPayload): Observable<CalendarEvent> {
    return this.http.put<CalendarEvent>(`${this.base}/${id}`, event);
  }

  deleteEvent(id: number): Observable<{ ok: boolean }> {
    return this.http.delete<{ ok: boolean }>(`${this.base}/${id}`);
  }
}
