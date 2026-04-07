import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatChipsModule } from '@angular/material/chips';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { Router } from '@angular/router';
import { CalendarEvent, EventService } from '../../core/services/event.service';
import { AuthService } from '../../core/services/auth.service';
import { EventFormComponent } from '../event-form/event-form.component';

@Component({
  selector: 'app-event-list',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatToolbarModule,
    MatDialogModule,
    MatProgressSpinnerModule,
    MatChipsModule,
    MatSnackBarModule,
  ],
  templateUrl: './event-list.component.html',
  styleUrl: './event-list.component.scss',
})
export class EventListComponent implements OnInit {
  private readonly eventService = inject(EventService);
  private readonly authService = inject(AuthService);
  private readonly dialog = inject(MatDialog);
  private readonly snackBar = inject(MatSnackBar);
  private readonly router = inject(Router);

  events: CalendarEvent[] = [];
  loading = false;
  displayedColumns: string[] = ['title', 'start', 'tags', 'actions'];

  ngOnInit(): void {
    this.loadEvents();
  }

  loadEvents(): void {
    this.loading = true;
    this.eventService.getEvents().subscribe({
      next: (events) => {
        this.events = events;
        this.loading = false;
      },
      error: () => {
        this.loading = false;
        this.snackBar.open('Failed to load events.', 'Dismiss', { duration: 4000 });
      },
    });
  }

  openNewEventDialog(): void {
    const ref = this.dialog.open(EventFormComponent, {
      data: null,
      width: '500px',
    });

    ref.afterClosed().subscribe((result: CalendarEvent | undefined) => {
      if (result) {
        this.loadEvents();
        this.snackBar.open('Event created.', 'Dismiss', { duration: 3000 });
      }
    });
  }

  openEditDialog(event: CalendarEvent): void {
    const ref = this.dialog.open(EventFormComponent, {
      data: event,
      width: '500px',
    });

    ref.afterClosed().subscribe((result: CalendarEvent | undefined) => {
      if (result) {
        this.loadEvents();
        this.snackBar.open('Event updated.', 'Dismiss', { duration: 3000 });
      }
    });
  }

  deleteEvent(event: CalendarEvent): void {
    if (!confirm(`Delete event "${event.title}"?`)) {
      return;
    }

    this.eventService.deleteEvent(event.id).subscribe({
      next: () => {
        this.loadEvents();
        this.snackBar.open('Event deleted.', 'Dismiss', { duration: 3000 });
      },
      error: () => {
        this.snackBar.open('Failed to delete event.', 'Dismiss', { duration: 4000 });
      },
    });
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/login']);
  }
}
