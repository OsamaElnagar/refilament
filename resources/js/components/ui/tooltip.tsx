import { Tooltip as TooltipPrimitive } from "@base-ui/react/tooltip"
import * as React from "react"

import { cn } from "@/lib/utils"

function TooltipProvider({
  delay = 0,
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Provider>) {
  return (
    <TooltipPrimitive.Provider
      data-slot="tooltip-provider"
      delay={delay}
      {...props}
    />
  )
}

function Tooltip({
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Root>) {
  return <TooltipPrimitive.Root data-slot="tooltip" {...props} />
}

function TooltipTrigger({
  ...props
}: React.ComponentProps<typeof TooltipPrimitive.Trigger>) {
  return <TooltipPrimitive.Trigger data-slot="tooltip-trigger" {...props} />
}

function TooltipContent({
  className,
  sideOffset = 4,
  alignOffset = 0,
  children,
  side = "top",
  align = "center",
  ...props
}: Omit<React.ComponentProps<typeof TooltipPrimitive.Popup>, "align" | "side"> & {
  alignOffset?: number
  sideOffset?: number
  side?: "top" | "right" | "bottom" | "left"
  align?: "start" | "center" | "end"
}) {
  return (
    <TooltipPrimitive.Portal>
      <TooltipPrimitive.Positioner
        side={side}
        sideOffset={sideOffset}
        align={align}
        alignOffset={alignOffset}
        className="isolate z-50"
      >
        <TooltipPrimitive.Popup
          data-slot="tooltip-content"
          className={cn(
            "bg-primary text-primary-foreground data-[open]:transition-[opacity,transform] data-starting-style:opacity-0 data-starting-style:scale-95 data-ending-style:opacity-0 data-ending-style:scale-95 data-[side=bottom]:data-starting-style:translate-y-2 data-[side=left]:data-starting-style:-translate-x-2 data-[side=right]:data-starting-style:translate-x-2 data-[side=top]:data-starting-style:-translate-y-2 data-[side=bottom]:data-ending-style:translate-y-2 data-[side=left]:data-ending-style:-translate-x-2 data-[side=right]:data-ending-style:translate-x-2 data-[side=top]:data-ending-style:-translate-y-2 z-50 max-w-sm rounded-md px-3 py-1.5 text-xs",
            className
          )}
          {...props}
        >
          {children}
          <TooltipPrimitive.Arrow
            render={
              <svg
                width="10"
                height="5"
                viewBox="0 0 10 5"
                className="fill-primary z-50"
              >
                <path d="M0 0 L10 0 L5 5 Z" />
              </svg>
            }
          />
        </TooltipPrimitive.Popup>
      </TooltipPrimitive.Positioner>
    </TooltipPrimitive.Portal>
  )
}

export { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider }
