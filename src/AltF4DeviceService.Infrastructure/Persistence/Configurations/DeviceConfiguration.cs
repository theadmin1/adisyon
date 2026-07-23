using AltF4DeviceService.Domain.Entities;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace AltF4DeviceService.Infrastructure.Persistence.Configurations;

public class DeviceConfiguration : IEntityTypeConfiguration<Device>
{
    public void Configure(EntityTypeBuilder<Device> builder)
    {
        builder.ToTable("Devices");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.DeviceUuid)
            .IsRequired()
            .HasMaxLength(64);

        builder.HasIndex(x => x.DeviceUuid)
            .IsUnique();

        builder.Property(x => x.DeviceCode)
            .IsRequired()
            .HasMaxLength(50);

        builder.Property(x => x.DeviceName)
            .HasMaxLength(100);

        builder.Property(x => x.IsActive)
            .IsRequired();

        builder.Property(x => x.CreatedAt)
            .IsRequired();
    }
}
