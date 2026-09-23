# Cheats for rest-bookings-controller-schema

- **step-1/**: the full schema-driven controller (correct routes, shapes, schema, pagination,
  links, validation, overlap detection) and the updated office screen/widget, but every
  permission callback only checks "is the user logged in" and the list is not restricted to
  the customer's own bookings. Guests get 401 and the UI keeps working, but customers can read,
  list, edit and self-confirm other people's bookings and use `context=edit`.
  Expected: permission tests fail -> step-1 reward 0.
- **step-2/** (on top of the step-1 oracle): a v2 controller that maps the v1 data into the new
  shape and adds the deprecation headers, without persisting the booking's time zone (no table
  change, so every booking reports the site timezone) and with amounts always multiplied by 100
  (wrong for JPY/KWD). Expected: time-zone and money tests fail -> step-2 reward 0.
