import { t } from "visual/utils/i18n";

export const title = t("Icon");

export function getItems() {
  return [
    {
      id: "settingsTabs",
      type: "tabs-dev",
      config: {
        align: "start"
      },
      devices: "desktop",
      tabs: [
        {
          id: "moreSettingsAdvanced",
          label: t("Advanced"),
          icon: "nc-cog",
          options: [
            {
              id: "hoverTransition",
              label: t("Hover Transition"),
              position: 100,
              type: "slider-dev",
              config: {
                min: 0,
                max: 99,
                units: [{ title: "ms", value: "ms" }]
              }
            }
          ]
        }
      ]
    }
  ];
}
