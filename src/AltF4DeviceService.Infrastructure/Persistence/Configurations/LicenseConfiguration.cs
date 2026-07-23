using AltF4DeviceService.Domain.Entities;
using Microsoft.EntityFrameworkCore;
using Microsoft.EntityFrameworkCore.Metadata.Builders;

namespace AltF4DeviceService.Infrastructure.Persistence.Configurations;

public class LicenseConfiguration : IEntityTypeConfiguration<License>
{
    public void Configure(EntityTypeBuilder<License> builder)
    {
        builder.ToTable("Licenses");

        builder.HasKey(x => x.Id);

        builder.Property(x => x.LicenseKey)
            .IsRequired()
            .HasMaxLength(128);

        builder.Property(x => x.DeviceToken)
            .IsRequired()
            .HasMaxLength(256);

        builder.Property(x => x.Status)
            .HasConversion<string>()
            .IsRequired()
            .HasMaxLength(32);

        builder.Property(x => x.CreatedAt)
            .IsRequired();
    }
}
