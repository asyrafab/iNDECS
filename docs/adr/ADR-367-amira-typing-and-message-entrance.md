# ADR-367: AMIRA typing and conversation-box entrance

Date: 2026-11-06. Status: Accepted by the programme owner.

## Decision

Each requested local-guidance reply reveals its text quickly in reading order.
User and reply boxes enter with a gentle enlargement and fade on each exchange.
Reserve the reply's final text height during typing to avoid repeated layout
jumps. Keep the existing single-exchange display and source/availability labels.

Use shared duration, entrance-scale and easing tokens. The avatar's contained
orbit responds while text appears. A new request cancels the old reveal and
shows only its own reply. Reduced motion shows complete text immediately;
enabling it or hiding the page completes an active reveal. Announce the complete
reply through the existing polite live region, using aria-busy during typing.
Insert all content as text. No model, network, audio or record changes.
