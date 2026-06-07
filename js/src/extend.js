import { Admin } from 'flarum/common/extenders';
import app from 'flarum/admin/app';

export default [
  new Admin()
    .setting(() => ({
      setting: 'onair.default_provider',
      type: 'select',
      label: app.translator.trans('onair.admin.settings.default_provider_label'),
      help: app.translator.trans('onair.admin.settings.default_provider_help'),
      options: { twitch: 'Twitch', youtube: 'YouTube' },
      default: 'twitch',
    }))
    .setting(() => ({
      setting: 'onair.poll_interval',
      type: 'number',
      label: app.translator.trans('onair.admin.settings.poll_interval_label'),
      help: app.translator.trans('onair.admin.settings.poll_interval_help'),
      min: 10,
      default: 30,
    }))
    .setting(() => ({
      setting: 'onair.max_stream_hours',
      type: 'number',
      label: app.translator.trans('onair.admin.settings.max_hours_label'),
      help: app.translator.trans('onair.admin.settings.max_hours_help'),
      min: 1,
      default: 12,
    }))
    .permission(
      () => ({
        icon: 'fa-solid fa-tower-broadcast',
        label: app.translator.trans('onair.admin.permissions.broadcast'),
        permission: 'onair.broadcast',
      }),
      'start',
      95
    )
    .permission(
      () => ({
        icon: 'fa-solid fa-user-shield',
        label: app.translator.trans('onair.admin.permissions.manage'),
        permission: 'onair.manage',
      }),
      'moderate',
      95
    ),
];
