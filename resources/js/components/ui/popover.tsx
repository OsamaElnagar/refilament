import { Popover as PopoverPrimitive } from "@base-ui/react/popover"
import * as React from "react"

import { cn } from "@/lib/utils"

function Popover({
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Root>) {
  return <PopoverPrimitive.Root data-slot="popover" {...props} />
}

function PopoverTrigger({
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Trigger>) {
  return <PopoverPrimitive.Trigger data-slot="popover-trigger" {...props} />
}

function PopoverContent({
  className,
  side = "bottom",
  sideOffset = 4,
  align = "center",
  alignOffset = 0,
  children,
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Positioner> & {
  side?: "top" | "right" | "bottom" | "left"
  sideOffset?: number
  align?: "start" | "center" | "end"
  alignOffset?: number
}) {
  return (
    <PopoverPrimitive.Portal data-slot="popover-portal">
      <PopoverPrimitive.Positioner
        side={side}
        sideOffset={sideOffset}
        align={align}
        alignOffset={alignOffset}
        className="isolate z-50"
        {...props}
      >
        <PopoverPrimitive.Popup
          data-slot="popover-content"
          className={cn(
            "bg-popover text-popover-foreground data-[open]:transition-[opacity,transform] data-starting-style:opacity-0 data-starting-style:scale-95 data-ending-style:opacity-0 data-ending-style:scale-95 data-[side=bottom]:data-starting-style:translate-y-2 data-[side=left]:data-starting-style:-translate-x-2 data-[side=right]:data-starting-style:translate-x-2 data-[side=top]:data-starting-style:-translate-y-2 data-[side=bottom]:data-ending-style:translate-y-2 data-[side=left]:data-ending-style:-translate-x-2 data-[side=right]:data-ending-style:translate-x-2 data-[side=top]:data-ending-style:-translate-y-2 z-50 min-w-[8rem] origin-(--transform-origin) overflow-hidden rounded-md border p-3 shadow-md outline-none",
            className
          )}
        >
          {children}
        </PopoverPrimitive.Popup>
      </PopoverPrimitive.Positioner>
    </PopoverPrimitive.Portal>
  )
}

function PopoverTitle({
  className,
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Title>) {
  return (
    <PopoverPrimitive.Title
      data-slot="popover-title"
      className={cn("text-foreground font-semibold", className)}
      {...props}
    />
  )
}

function PopoverDescription({
  className,
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Description>) {
  return (
    <PopoverPrimitive.Description
      data-slot="popover-description"
      className={cn("text-muted-foreground text-sm leading-relaxed", className)}
      {...props}
    />
  )
}

function PopoverClose({
  className,
  ...props
}: React.ComponentProps<typeof PopoverPrimitive.Close>) {
  return <PopoverPrimitive.Close data-slot="popover-close" className={cn("outline-hidden", className)} {...props} />
}

export {
  Popover,
  PopoverClose,
  PopoverContent,
  PopoverDescription,
  PopoverTitle,
  PopoverTrigger,
}
