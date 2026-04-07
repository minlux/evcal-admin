import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatDialogModule, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { COMMA, ENTER } from '@angular/cdk/keycodes';
import { MatChipInputEvent } from '@angular/material/chips';
import { CalendarEvent, CalendarEventPayload, EventService } from '../../core/services/event.service';

@Component({
  selector: 'app-event-form',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatChipsModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './event-form.component.html',
  styleUrl: './event-form.component.scss',
})
export class EventFormComponent implements OnInit {
  private readonly dialogRef = inject(MatDialogRef<EventFormComponent>);
  private readonly dialogData: CalendarEvent | null = inject(MAT_DIALOG_DATA, { optional: true });
  private readonly eventService = inject(EventService);

  readonly separatorKeyCodes = [ENTER, COMMA] as const;

  isEditMode = false;
  loading = false;
  errorMessage = '';

  formData: {
    title: string;
    start: string[];
    tags: string[];
    description: string;
  } = {
    title: '',
    start: [''],
    tags: [],
    description: '',
  };

  ngOnInit(): void {
    if (this.dialogData) {
      this.isEditMode = true;
      this.formData = {
        title: this.dialogData.title,
        start: this.dialogData.start.length > 0
          ? this.dialogData.start.map((s) => s.replace(' ', 'T'))
          : [''],
        tags: [...this.dialogData.tags],
        description: this.dialogData.description,
      };
    }
  }

  addDate(): void {
    this.formData.start.push('');
  }

  removeDate(index: number): void {
    if (this.formData.start.length > 1) {
      this.formData.start.splice(index, 1);
    }
  }

  addTag(event: MatChipInputEvent): void {
    const value = (event.value || '').trim();
    if (value && !this.formData.tags.includes(value)) {
      this.formData.tags.push(value);
    }
    event.chipInput?.clear();
  }

  removeTag(tag: string): void {
    const idx = this.formData.tags.indexOf(tag);
    if (idx >= 0) {
      this.formData.tags.splice(idx, 1);
    }
  }

  onCancel(): void {
    this.dialogRef.close();
  }

  onSave(): void {
    if (!this.formData.title.trim()) {
      return;
    }

    this.loading = true;
    this.errorMessage = '';

    const payload: CalendarEventPayload = {
      title: this.formData.title.trim(),
      start: this.formData.start
        .filter((s) => s.trim() !== '')
        .map((s) => s.replace('T', ' ')),
      tags: this.formData.tags,
      description: this.formData.description,
    };

    const request$ = this.isEditMode && this.dialogData
      ? this.eventService.updateEvent(this.dialogData.id, payload)
      : this.eventService.createEvent(payload);

    request$.subscribe({
      next: (event) => {
        this.loading = false;
        this.dialogRef.close(event);
      },
      error: (err) => {
        this.loading = false;
        this.errorMessage = err?.error?.error ?? 'Failed to save event.';
      },
    });
  }
}
