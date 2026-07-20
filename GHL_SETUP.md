# GoHighLevel launch setup

The website form is complete but intentionally fails closed until verified GHL configuration exists.

1. Confirm the final phone/email/SMS consent language with counsel or the messaging provider.
2. Create/map contact fields for every form answer and attribution field.
3. Configure an idempotent server-side or GHL form endpoint that upserts the contact and one audit opportunity. Never expose a private API key in `assets/config.js`.
4. Set the new-audit pipeline/stage, internal notification, confirmation email/text, and no-booking follow-up task.
5. Configure the GHL calendar and status transitions for booked, completed, rescheduled, canceled, qualified, proposal sent, won, and lost.
6. Put only the public endpoint URL in `assets/config.js`.
7. Submit the same fake contractor twice. Confirm one contact and one opportunity, mapped answers, attribution, notifications, messages, task, thank-you redirect, and calendar behavior.
8. Test STOP/opt-out and error recovery. Record screenshots/logs of the pass.
