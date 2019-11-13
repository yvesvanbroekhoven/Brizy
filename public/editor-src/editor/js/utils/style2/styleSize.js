import { defaultValueValue } from "visual/utils/onChange";

export function styleSizeWidth({ v, device, state }) {
  return defaultValueValue({ v, key: "width", device, state });
}

export function styleSizeHeight({ v, device }) {
  return defaultValueValue({ v, key: "height", device });
}

export function styleSizeSize({ v, device }) {
  return defaultValueValue({ v, key: "size", device });
}

export function styleSizeContainerSize({ v, device, state }) {
  const containerSize = defaultValueValue({
    v,
    key: "containerSize",
    device,
    state
  });

  return containerSize;
}

export function styleSizeTextSpacing({ v, device, state }) {
  return `${defaultValueValue({ v, key: "textSpacing", device, state })}`;
}
